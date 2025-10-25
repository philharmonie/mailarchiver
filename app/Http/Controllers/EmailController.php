<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Email;
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

            // Use Scout for full-text search if available
            if (config('scout.driver') !== null) {
                // Get all matching email IDs via Scout
                $searchResults = Email::search($search)->take(1000)->get();

                // Filter by user ownership and bcc_map_type
                $emailIds = $searchResults->filter(function ($email) use ($user) {
                    return $this->isUserAuthorizedForEmail($email, $user->email);
                })->pluck('id')->toArray();

                if (! empty($emailIds)) {
                    $query->whereIn('id', $emailIds);
                } else {
                    // No results, return empty query
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Fallback to LIKE search
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_address', 'like', "%{$search}%")
                        ->orWhere('body_text', 'like', "%{$search}%");
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
        $email->makeVisible(['body_html', 'body_text', 'headers']);

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
}
