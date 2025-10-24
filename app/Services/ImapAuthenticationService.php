<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class ImapAuthenticationService
{
    /**
     * Authenticate a user against the IMAP server
     *
     * @param  string  $email  User's email address
     * @param  string  $password  User's password
     * @return User|null Returns User if authentication successful, null otherwise
     */
    public function authenticate(string $email, string $password): ?User
    {
        // Get IMAP server configuration from archive account
        $archiveAccount = \App\Models\ImapAccount::where('is_active', true)->first();

        if (! $archiveAccount) {
            Log::warning('No active IMAP account found for authentication');

            return null;
        }

        // Try to connect to IMAP server with user's credentials
        $cm = new ClientManager;

        $config = [
            'host' => $archiveAccount->host,
            'port' => $archiveAccount->port,
            'encryption' => $archiveAccount->encryption,
            'validate_cert' => $archiveAccount->validate_cert,
            'username' => $email,
            'password' => $password,
            'protocol' => 'imap',
        ];

        try {
            $client = $cm->make($config);
            $client->connect();

            // If connection successful, authentication is valid
            Log::info('IMAP authentication successful', [
                'email' => $email,
            ]);

            // Auto-create or update user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $this->extractNameFromEmail($email),
                    'password' => Hash::make(Str::random(32)), // Random password, not used for auth
                    'role' => 'user',
                ]
            );

            // Disconnect
            if ($client->isConnected()) {
                $client->disconnect();
            }

            return $user;
        } catch (ConnectionFailedException $e) {
            Log::warning('IMAP authentication failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('IMAP authentication error', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract name from email address
     */
    protected function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);

        return ucfirst($parts[0] ?? 'User');
    }
}
