<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Email;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
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

        // Filter emails to show only those where user is sender or recipient
        $query->where(function ($q) use ($user) {
            $q->where('from_address', $user->email)
                ->orWhereJsonContains('to_addresses', $user->email);
        });

        if ($request->filled('search')) {
            $search = $request->input('search');

            // Use Scout for full-text search if available
            if (config('scout.driver') !== null) {
                // Get all matching email IDs via Scout
                $searchResults = Email::search($search)->take(1000)->get();

                // Filter by user ownership
                $emailIds = $searchResults->filter(function ($email) use ($user) {
                    return $email->from_address === $user->email
                        || (is_array($email->to_addresses) && in_array($user->email, $email->to_addresses));
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

        // Check if user is authorized to view this email (sender or recipient)
        $isAuthorized = $email->from_address === $user->email
            || (is_array($email->to_addresses) && in_array($user->email, $email->to_addresses));

        if (! $isAuthorized) {
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

        // Check if user is authorized to download this email
        $isAuthorized = $email->from_address === $user->email
            || (is_array($email->to_addresses) && in_array($user->email, $email->to_addresses));

        if (! $isAuthorized) {
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
