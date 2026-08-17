<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Email;
use App\Services\GobdExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    /**
     * Check if user is authorized to access this email based on bcc_map_type
     */
    protected function isUserAuthorizedForEmail(Email $email, string $userEmail): bool
    {
        $isSender = $email->from_address === $userEmail;
        $isRecipient = (is_array($email->to_addresses) && in_array($userEmail, $email->to_addresses))
            || (is_array($email->cc_addresses) && in_array($userEmail, $email->cc_addresses))
            || (is_array($email->bcc_addresses) && in_array($userEmail, $email->bcc_addresses));

        return match ($email->bcc_map_type) {
            'sender' => $isSender,
            'recipient' => $isRecipient,
            'both' => $isSender || $isRecipient,
            default => $isSender || $isRecipient, // Fallback for null/old emails
        };
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Admin cannot view emails, only statistics
        if ($user->isAdmin()) {
            abort(403, 'Admins cannot view email list. Only statistics are available on the dashboard.');
        }

        $query = Email::query()
            ->with('attachments:id,email_id,filename,size_bytes,mime_type')
            ->orderBy('received_at', 'desc');

        // Filter emails based on bcc_map_type
        $query->where(function ($q) use ($user) {
            // sender type: only show if user is sender
            $q->where(function ($subQ) use ($user) {
                $subQ->where('bcc_map_type', 'sender')
                    ->where('from_address', $user->email);
            })
                // recipient type: only show if user is recipient
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('bcc_map_type', 'recipient')
                        ->where(function ($recipientQ) use ($user) {
                            $recipientQ->whereJsonContains('to_addresses', $user->email)
                                ->orWhereJsonContains('cc_addresses', $user->email)
                                ->orWhereJsonContains('bcc_addresses', $user->email);
                        });
                })
                // both type: show if user is sender or recipient
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('bcc_map_type', 'both')
                        ->where(function ($bothQ) use ($user) {
                            $bothQ->where('from_address', $user->email)
                                ->orWhereJsonContains('to_addresses', $user->email)
                                ->orWhereJsonContains('cc_addresses', $user->email)
                                ->orWhereJsonContains('bcc_addresses', $user->email);
                        });
                })
                // null/old emails: fallback to old behavior
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->whereNull('bcc_map_type')
                        ->where(function ($nullQ) use ($user) {
                            $nullQ->where('from_address', $user->email)
                                ->orWhereJsonContains('to_addresses', $user->email);
                        });
                });
        });

        if ($request->filled('search')) {
            $search = $request->input('search');
            $driver = config('scout.driver');

            // Only use Scout for external engines (e.g. Meilisearch). The
            // "collection" driver loads the entire table into memory to filter
            // in PHP, which exhausts memory on large mailboxes, so fall back to
            // a direct SQL search for it.
            if ($driver !== null && $driver !== 'collection') {
                // Hydrate only the columns needed for the ownership check to
                // avoid pulling large body/raw_email columns into memory.
                $searchResults = Email::search($search)
                    ->take(1000)
                    ->query(fn ($q) => $q->select([
                        'id', 'from_address', 'to_addresses', 'cc_addresses',
                        'bcc_addresses', 'bcc_map_type',
                    ]))
                    ->get();

                $emailIds = $searchResults->filter(function (Email $email) use ($user) {
                    return $this->isUserAuthorizedForEmail($email, $user->email);
                })->pluck('id')->all();

                if ($emailIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('id', $emailIds);
                }
            } else {
                // Direct SQL search across the same fields Scout would index.
                // The ownership filter above already restricts $query, so this
                // only narrows the user's own emails further.
                $query->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('subject', 'like', $like)
                        ->orWhere('from_address', 'like', $like)
                        ->orWhere('from_name', 'like', $like)
                        ->orWhere('message_id', 'like', $like)
                        ->orWhere('to_addresses', 'like', $like)
                        ->orWhere('cc_addresses', 'like', $like)
                        ->orWhere('body_text', 'like', $like);
                });
            }
        }

        if ($request->filled('from')) {
            $query->where('from_address', 'like', "%{$request->input('from')}%");
        }

        if ($request->filled('date_from')) {
            $query->where('received_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('received_at', '<=', $request->input('date_to'));
        }

        $emails = $query->paginate(25)->withQueryString();

        return Inertia::render('emails/index', [
            'emails' => $emails,
            'filters' => $request->only(['search', 'from', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, Email $email): Response
    {
        $user = $request->user();

        // Admin cannot view email details
        if ($user->isAdmin()) {
            abort(403, 'Admins cannot view email details. Only statistics are available on the dashboard.');
        }

        // Check if user is authorized to view this email based on bcc_map_type
        if (! $this->isUserAuthorizedForEmail($email, $user->email)) {
            abort(403, 'You are not authorized to view this email.');
        }

        $email->load('attachments', 'auditLogs.user');
        $email->makeVisible(['body_text', 'headers']);

        // Read out of raw_email rather than off a column of its own - see
        // Email::deriveBodyHtml(). The view reads it under the name it always
        // had.
        $email->setAttribute('body_html', $email->deriveBodyHtml());

        AuditLog::log($email, 'viewed', 'Email viewed by user');

        return Inertia::render('emails/show', [
            'email' => $email,
        ]);
    }

    public function download(Request $request, Email $email)
    {
        $user = $request->user();

        // Admin cannot download emails
        if ($user->isAdmin()) {
            abort(403, 'Admins cannot download emails.');
        }

        // Check if user is authorized to download this email based on bcc_map_type
        if (! $this->isUserAuthorizedForEmail($email, $user->email)) {
            abort(403, 'You are not authorized to download this email.');
        }

        // Get raw email (decompress if needed)
        $rawEmail = $email->getRawEmailDecompressed();

        // Generate filename: subject + date + .eml
        $subject = $email->subject ?: 'email';
        $date = $email->received_at->format('Y-m-d');
        $filename = sprintf(
            '%s_%s.eml',
            preg_replace('/[^a-z0-9_-]/i', '_', $subject),
            $date
        );

        AuditLog::log($email, 'downloaded', 'Email downloaded by user');

        return response($rawEmail, 200)
            ->header('Content-Type', 'message/rfc822')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Content-Length', strlen($rawEmail));
    }

    public function exportPage(Request $request): Response
    {
        $user = $request->user();

        // Admin cannot export emails
        if ($user->isAdmin()) {
            abort(403, 'Admins cannot export emails.');
        }

        // Get email count for user
        $emailCount = Email::where(function ($q) use ($user) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('bcc_map_type', 'sender')
                    ->where('from_address', $user->email);
            })
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('bcc_map_type', 'recipient')
                        ->where(function ($recipientQ) use ($user) {
                            $recipientQ->whereJsonContains('to_addresses', $user->email)
                                ->orWhereJsonContains('cc_addresses', $user->email)
                                ->orWhereJsonContains('bcc_addresses', $user->email);
                        });
                })
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('bcc_map_type', 'both')
                        ->where(function ($bothQ) use ($user) {
                            $bothQ->where('from_address', $user->email)
                                ->orWhereJsonContains('to_addresses', $user->email)
                                ->orWhereJsonContains('cc_addresses', $user->email)
                                ->orWhereJsonContains('bcc_addresses', $user->email);
                        });
                })
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->whereNull('bcc_map_type')
                        ->where(function ($nullQ) use ($user) {
                            $nullQ->where('from_address', $user->email)
                                ->orWhereJsonContains('to_addresses', $user->email);
                        });
                });
        })->count();

        return Inertia::render('emails/export', [
            'emailCount' => $emailCount,
        ]);
    }

    public function exportGobd(Request $request, GobdExportService $exportService)
    {
        $user = $request->user();

        // Admin cannot export emails
        if ($user->isAdmin()) {
            abort(403, 'Admins cannot export emails.');
        }

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $dateFrom = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : null;
        $dateTo = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : null;

        // Export emails for this user only
        $result = $exportService->export($dateFrom, $dateTo, null, $user->email);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        // Log the export
        AuditLog::create([
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'user_id' => $user->id,
            'action' => 'gobd_export',
            'description' => sprintf(
                'User exported %d emails (GoBD-compliant) from %s to %s',
                $result['count'],
                $dateFrom ? $dateFrom->format('Y-m-d') : 'beginning',
                $dateTo ? $dateTo->format('Y-m-d') : 'today'
            ),
        ]);

        // Return the file for download
        return response()->download($result['file'])->deleteFileAfterSend(true);
    }
}
