<?php

use App\Http\Controllers\Api\MailcowWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
// Mailcow BCC Webhook - receives emails in real-time
use Laravel\Fortify\Features;

Route::post('/api/webhook/mailcow', [MailcowWebhookController::class, 'handle'])
    ->name('api.webhook.mailcow');

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Illuminate\Http\Request $request) {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        // Admins see global statistics and per-account breakdown
        if ($isAdmin) {
            $totalEmails = App\Models\Email::count();
            $totalSize = App\Models\Email::sum('size_bytes');
            $totalAccounts = App\Models\ImapAccount::count();
            $activeAccounts = App\Models\ImapAccount::where('is_active', true)->count();

            // Get stats per IMAP account
            $accountStats = App\Models\ImapAccount::withCount('emails')
                ->selectRaw('id, name, username, is_active')
                ->selectRaw('(SELECT SUM(size_bytes) FROM emails WHERE emails.imap_account_id = imap_accounts.id) as total_size')
                ->orderByDesc('emails_count')
                ->limit(10)
                ->get()
                ->map(function ($account) {
                    return [
                        'name' => $account->name,
                        'username' => $account->username,
                        'is_active' => $account->is_active,
                        'emails_count' => $account->emails_count,
                        'total_size' => $account->total_size ?? 0,
                    ];
                });

            return Inertia::render('dashboard', [
                'stats' => [
                    'total_emails' => $totalEmails,
                    'total_size_bytes' => $totalSize,
                    'total_accounts' => $totalAccounts,
                    'active_accounts' => $activeAccounts,
                ],
                'account_stats' => $accountStats,
                'is_admin' => true,
            ]);
        }

        // Regular users see only their email stats (respecting bcc_map_type)
        $userEmailsQuery = App\Models\Email::where(function ($q) use ($user) {
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

        $totalEmails = $userEmailsQuery->count();
        $totalSize = $userEmailsQuery->sum('size_bytes');

        // Emails this month
        $emailsThisMonth = (clone $userEmailsQuery)
            ->whereMonth('received_at', now()->month)
            ->whereYear('received_at', now()->year)
            ->count();

        // Recent emails for display
        $recentEmails = (clone $userEmailsQuery)
            ->with('attachments:id,email_id,filename,size_bytes')
            ->orderBy('received_at', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'total_emails' => $totalEmails,
                'total_size_bytes' => $totalSize,
                'emails_this_month' => $emailsThisMonth,
            ],
            'recent_emails' => $recentEmails,
            'is_admin' => false,
        ]);
    })->name('dashboard');

    Route::get('emails', [App\Http\Controllers\EmailController::class, 'index'])->name('emails.index');
    Route::get('emails/{email}', [App\Http\Controllers\EmailController::class, 'show'])->name('emails.show');
    Route::get('emails/{email}/download', [App\Http\Controllers\EmailController::class, 'download'])->name('emails.download');

    // GoBD Export
    Route::get('export', [App\Http\Controllers\EmailController::class, 'exportPage'])->name('export');
    Route::post('export', [App\Http\Controllers\EmailController::class, 'exportGobd'])->name('export.gobd');
});

// Admin-only: IMAP Account Management
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::resource('imap-accounts', App\Http\Controllers\ImapAccountController::class);
    Route::post('imap-accounts/{imapAccount}/test', [App\Http\Controllers\ImapAccountController::class, 'test'])
        ->name('imap-accounts.test');
});

// User Settings (Profile, Password, 2FA, Appearance)
require __DIR__.'/settings.php';
