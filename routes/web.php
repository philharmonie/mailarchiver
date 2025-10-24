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
    Route::get('dashboard', function () {
        $accounts = App\Models\ImapAccount::withCount('emails')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'total_emails' => $account->total_emails,
                    'total_size_bytes' => $account->total_size_bytes,
                    'formatted_size' => $account->formatted_size,
                    'last_fetch_at' => $account->last_fetch_at,
                    'is_active' => $account->is_active,
                ];
            });

        $totalEmails = App\Models\Email::count();
        $totalSize = App\Models\Email::sum('size_bytes');

        return Inertia::render('dashboard', [
            'accounts' => $accounts,
            'stats' => [
                'total_emails' => $totalEmails,
                'total_size_bytes' => $totalSize,
                'total_accounts' => $accounts->count(),
                'active_accounts' => $accounts->where('is_active', true)->count(),
            ],
        ]);
    })->name('dashboard');

    Route::get('emails', [App\Http\Controllers\EmailController::class, 'index'])->name('emails.index');
    Route::get('emails/{email}', [App\Http\Controllers\EmailController::class, 'show'])->name('emails.show');

    Route::resource('imap-accounts', App\Http\Controllers\ImapAccountController::class);
    Route::post('imap-accounts/{imapAccount}/test', [App\Http\Controllers\ImapAccountController::class, 'test'])
        ->name('imap-accounts.test');
});

require __DIR__.'/settings.php';
