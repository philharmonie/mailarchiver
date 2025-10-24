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
     * Fetch and archive new emails from the configured folder
     */
    public function fetchAndArchiveEmails(?int $limit = null, bool $fetchAll = false, ?callable $progressCallback = null): array
    {
        if (! isset($this->client) || ! isset($this->currentAccount)) {
            throw new \RuntimeException('Must connect to an account first using connectToAccount()');
        }

        $folder = $this->client->getFolder($this->currentAccount->folder);

        $query = $folder->query();

        // Fetch either all or just unseen emails
        if ($fetchAll) {
            // Fetch all emails (using ALL criterion)
            $query->whereAll();
        } else {
            // Only fetch unseen emails
            $query->unseen();
        }

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
                $archived[] = [
                    'email_id' => $email->id,
                    'message_id' => $email->message_id,
                    'subject' => $email->subject,
                ];

                $totalSize += $email->size_bytes;

                // Mark as seen after successful archiving
                $message->setFlag('Seen');

                // Update account statistics immediately
                $this->currentAccount->incrementStats(1, $email->size_bytes);

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
     */
    protected function archiveMessage(Message $message): \App\Models\Email
    {
        // Use the IMAP-specific parser that properly extracts headers
        $email = $this->emailParser->parseAndStoreFromImap($message);

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
