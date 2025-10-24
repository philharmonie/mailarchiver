<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImapAccount;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MailcowWebhookController extends Controller
{
    public function __construct(
        protected EmailParserService $emailParser
    ) {}

    /**
     * Handle incoming email from Mailcow BCC
     */
    public function handle(Request $request)
    {
        // Check if Mailcow is enabled
        if (! Setting::isMailcowEnabled()) {
            return response()->json(['error' => 'Mailcow integration is disabled'], 403);
        }

        try {
            // Get the raw email from the request
            $rawEmail = $request->getContent();

            if (empty($rawEmail)) {
                return response()->json(['error' => 'No email data provided'], 400);
            }

            // Parse the email to get recipient info
            $email = $this->emailParser->parseAndStore($rawEmail);

            // Determine which user this email belongs to
            $recipientEmail = $email->to_addresses[0] ?? null;

            if (! $recipientEmail) {
                Log::warning('Email without recipient received via webhook', ['email_id' => $email->id]);

                return response()->json(['success' => true, 'message' => 'Email archived without user assignment']);
            }

            // Find or create user and IMAP account
            $user = $this->findOrCreateUser($recipientEmail);

            if ($user) {
                // Associate email with the user's IMAP account
                $email->update(['imap_account_id' => $user->imap_account_id]);
            }

            return response()->json([
                'success' => true,
                'email_id' => $email->id,
                'user_id' => $user?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Mailcow webhook failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to process email'], 500);
        }
    }

    /**
     * Find or create user based on email address
     */
    protected function findOrCreateUser(string $email): ?User
    {
        // Check if user already exists
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        // Create IMAP account (credentials will be set later via IMAP login)
        $imapAccount = ImapAccount::create([
            'name' => "Mailcow - {$email}",
            'host' => Setting::get('mailcow_imap_host', 'mail.example.com'),
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $email,
            'password' => Str::random(32), // Temporary password
            'folder' => 'INBOX',
            'is_active' => false, // Inactive until first IMAP login
        ]);

        // Create user
        $user = User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => bcrypt(Str::random(32)), // Temporary password
            'role' => 'user',
            'imap_account_id' => $imapAccount->id,
        ]);

        Log::info('Auto-created user from Mailcow webhook', [
            'user_id' => $user->id,
            'email' => $email,
        ]);

        return $user;
    }
}
