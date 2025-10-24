<?php

namespace App\Services;

use App\Models\ImapAccount;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Message;

class ImapService
{
    protected Client $client;

    protected EmailParserService $emailParser;

    protected ?ImapAccount $currentAccount = null;

    public function __construct(EmailParserService $emailParser)
    {
        $this->emailParser = $emailParser;
    }

    /**
     * Connect to IMAP server using ImapAccount model
     */
    public function connectToAccount(ImapAccount $account): void
    {
        $this->currentAccount = $account;

        $cm = new ClientManager;

        $config = [
            'host' => $account->host,
            'port' => $account->port,
            'encryption' => $account->encryption,
            'validate_cert' => $account->validate_cert,
            'username' => $account->username,
            'password' => $account->password,
            'protocol' => 'imap',
        ];

        $this->client = $cm->make($config);

        try {
            $this->client->connect();
        } catch (ConnectionFailedException $e) {
            Log::error('Failed to connect to IMAP server', [
                'error' => $e->getMessage(),
                'account' => $account->name,
                'host' => $account->host,
            ]);

            throw $e;
        }
    }

    /**
     * Fetch and archive emails from the configured folder
     */
    public function fetchAndArchiveEmails(?int $limit = null, ?callable $progressCallback = null): array
    {
        if (! isset($this->client) || ! isset($this->currentAccount)) {
            throw new \RuntimeException('Must connect to an account first using connectToAccount()');
        }

        $folder = $this->client->getFolder($this->currentAccount->folder);

        $query = $folder->query();

        // Always fetch all emails for BCC archive approach
        // We don't want to miss any emails based on read/unread status
        $query->whereAll();

        if ($limit) {
            $query->limit($limit);
        }

        // This can take a while for large mailboxes
        $messages = $query->get();
        $totalMessages = count($messages);

        Log::info('Fetched message list from IMAP', [
            'account' => $this->currentAccount->name,
            'total_messages' => $totalMessages,
        ]);

        $archived = [];
        $totalSize = 0;
        $current = 0;

        foreach ($messages as $message) {
            $current++;

            try {
                $email = $this->archiveMessage($message);

                // If null, email is a duplicate (already archived)
                if (! $email) {
                    Log::debug('Email already archived (duplicate), skipping', [
                        'account' => $this->currentAccount->name,
                        'message_id' => $message->getMessageId(),
                    ]);

                    // Delete from server if configured (even for duplicates)
                    if ($this->currentAccount->delete_after_archive) {
                        try {
                            $message->delete();
                            Log::info('Duplicate email deleted from server', [
                                'account' => $this->currentAccount->name,
                                'message_id' => $message->getMessageId(),
                            ]);
                        } catch (\Exception $deleteError) {
                            Log::error('Failed to delete duplicate email from server', [
                                'account' => $this->currentAccount->name,
                                'message_id' => $message->getMessageId(),
                                'error' => $deleteError->getMessage(),
                            ]);
                        }
                    }

                    // Call progress callback for duplicate (no error)
                    if ($progressCallback) {
                        $progressCallback($current, $totalMessages, null, null, true);
                    }

                    continue;
                }

                $archived[] = [
                    'email_id' => $email->id,
                    'message_id' => $email->message_id,
                    'subject' => $email->subject,
                ];

                $totalSize += $email->size_bytes;

                // Update account statistics immediately
                $this->currentAccount->incrementStats(1, $email->size_bytes);

                // Delete from server if configured (opt-in)
                if ($this->currentAccount->delete_after_archive) {
                    try {
                        $message->delete();

                        Log::info('Email deleted from server after archival', [
                            'account' => $this->currentAccount->name,
                            'email_id' => $email->id,
                            'message_id' => $email->message_id,
                        ]);

                        // Audit log for deletion
                        \App\Models\AuditLog::log($email, 'deleted_from_server', 'Email deleted from IMAP server after successful archival');
                    } catch (\Exception $deleteError) {
                        // Log deletion error but don't fail the archival
                        Log::error('Failed to delete email from server after archival', [
                            'account' => $this->currentAccount->name,
                            'email_id' => $email->id,
                            'error' => $deleteError->getMessage(),
                        ]);
                    }
                }

                // Call progress callback if provided
                if ($progressCallback) {
                    $progressCallback($current, $totalMessages, $email);
                }

                Log::info('Email archived successfully via IMAP', [
                    'account' => $this->currentAccount->name,
                    'email_id' => $email->id,
                    'message_id' => $email->message_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to archive email via IMAP', [
                    'account' => $this->currentAccount->name,
                    'error' => $e->getMessage(),
                    'message_id' => $message->getMessageId(),
                ]);

                // Call progress callback even for errors
                if ($progressCallback) {
                    $progressCallback($current, $totalMessages, null, $e);
                }
            }
        }

        return $archived;
    }

    /**
     * Archive a single IMAP message
     *
     * @return \App\Models\Email|null Returns null if email is a duplicate
     */
    protected function archiveMessage(Message $message): ?\App\Models\Email
    {
        // Use the IMAP-specific parser that properly extracts headers
        $email = $this->emailParser->parseAndStoreFromImap($message);

        // If null, email already exists (duplicate)
        if (! $email) {
            return null;
        }

        // Associate with current IMAP account
        $email->update(['imap_account_id' => $this->currentAccount->id]);

        return $email;
    }

    /**
     * Get the IMAP client
     */
    public function getClient(): Client
    {
        if (! isset($this->client)) {
            throw new \RuntimeException('Must connect to an account first using connectToAccount()');
        }

        return $this->client;
    }

    /**
     * Test IMAP connection
     */
    public function testConnection(): bool
    {
        try {
            if (! isset($this->client)) {
                throw new \RuntimeException('Must connect to an account first');
            }

            return $this->client->isConnected();
        } catch (\Exception $e) {
            Log::error('IMAP connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get folder list from IMAP server
     */
    public function getFolders(): array
    {
        if (! isset($this->client)) {
            $this->connect();
        }

        $folders = $this->client->getFolders();

        return $folders->map(fn ($folder) => $folder->name)->toArray();
    }

    /**
     * Disconnect from IMAP server
     */
    public function disconnect(): void
    {
        if (isset($this->client) && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
