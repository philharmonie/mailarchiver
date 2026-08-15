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

        // Override package-level options. webklex reads `options.rfc822` from
        // the global config (not per-account), and the default uses
        // \imap_rfc822_parse_headers, which emits a noisy E_WARNING
        // ("Missing address after comma") on perfectly normal mail with
        // trailing-comma address lists, surfacing as
        // "PHP Request Shutdown: Missing address after comma".
        $cm = new ClientManager([
            'options' => [
                'rfc822' => false,
                'soft_fail' => true,
            ],
        ]);

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
                'error' => EmailParserService::errorExcerpt($e),
                'account' => $account->name,
                'host' => $account->host,
            ]);

            throw $e;
        }
    }

    /**
     * Fetch and archive emails from the configured folder
     * Uses chunked processing to avoid memory exhaustion with large mailboxes
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

        // Get total message count first (without loading all messages)
        $totalMessages = $limit ?: $query->count();

        Log::info('Starting email fetch from IMAP', [
            'account' => $this->currentAccount->name,
            'total_messages' => $totalMessages,
            'chunked_processing' => true,
        ]);

        $archived = [];
        $totalSize = 0;
        $current = 0;
        $consecutiveChunkFailures = 0;
        $maxConsecutiveChunkFailures = 3;

        // Process emails in chunks to avoid memory exhaustion
        $chunkSize = 25; // Process 25 emails at a time
        $offset = 0;

        // With delete_after_archive the folder shrinks under the cursor: every
        // archived message is expunged, so what was page 2 becomes page 1.
        // Paging forward then steps over whole ranges, and the mail in them
        // stays behind for some later run to stumble over - which is also what
        // asks the server for pages past the end and produces "Chunk fetch
        // failed". Read the first page again instead, and remember the uids
        // already tried: a message that cannot be archived stays in the folder
        // and would otherwise hand us the same page forever.
        $shrinkingFolder = (bool) $this->currentAccount->delete_after_archive;
        $attemptedUids = [];
        $stuckOffset = 0;

        while ($current < $totalMessages) {
            // Fetch next chunk of messages
            $chunkQuery = $folder->query()->whereAll();

            // Apply limit to chunk. Mail that refused to be deleted stays at
            // the head of a shrinking folder, so ask for it *and* a full chunk
            // of fresh messages behind it - otherwise the same few would fill
            // every window from here on.
            $remainingMessages = $totalMessages - $current;
            $currentChunkSize = min($chunkSize, $remainingMessages) + $stuckOffset;

            // webklex limit($count, $page) expects a 1-indexed page number,
            // not a row offset. Convert our row offset accordingly - unless the
            // folder is shrinking, where the next unread message is always back
            // on page one.
            $page = $shrinkingFolder ? 1 : intdiv($offset, $chunkSize) + 1;
            $chunkQuery->limit($currentChunkSize, $page);

            try {
                $messages = $chunkQuery->get();
                $consecutiveChunkFailures = 0;
            } catch (\Throwable $chunkError) {
                $root = $chunkError;
                while ($root->getPrevious() !== null) {
                    $root = $root->getPrevious();
                }

                $consecutiveChunkFailures++;

                Log::error('Chunk fetch failed, skipping range', [
                    'account' => $this->currentAccount->name,
                    'offset' => $offset,
                    'page' => $page,
                    'chunk_size' => $currentChunkSize,
                    'error' => EmailParserService::errorExcerpt($chunkError),
                    'exception' => get_class($chunkError),
                    'root_exception' => get_class($root),
                    'root_message' => EmailParserService::errorExcerpt($root),
                    'consecutive_failures' => $consecutiveChunkFailures,
                ]);

                if ($consecutiveChunkFailures >= $maxConsecutiveChunkFailures) {
                    Log::warning('Stopping fetch after repeated chunk failures', [
                        'account' => $this->currentAccount->name,
                        'offset' => $offset,
                        'consecutive_failures' => $consecutiveChunkFailures,
                    ]);
                    break;
                }

                $offset += $currentChunkSize;
                $current += $currentChunkSize;

                continue;
            }

            if ($messages->isEmpty()) {
                break;
            }

            if ($shrinkingFolder) {
                $fresh = $messages->filter(
                    fn (Message $message) => ! isset($attemptedUids[$message->getUid()])
                );

                // What is left of this window is what could not be deleted, and
                // the next window has to reach past it.
                $stuckOffset = $messages->count() - $fresh->count();

                if ($fresh->isEmpty()) {
                    // The window covers the stuck mail plus a full chunk behind
                    // it, so nothing fresh here means nothing fresh left.
                    Log::warning('Stopping fetch, only undeletable mail left in folder', [
                        'account' => $this->currentAccount->name,
                        'stuck_messages' => $stuckOffset,
                        'processed' => $current,
                    ]);

                    break;
                }

                $messages = $fresh;
            }

            Log::debug('Processing email chunk', [
                'account' => $this->currentAccount->name,
                'chunk_size' => $messages->count(),
                'offset' => $offset,
                'current' => $current,
                'total' => $totalMessages,
            ]);

            foreach ($messages as $message) {
                $current++;
                $attemptedUids[$message->getUid()] = true;

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
                                    'error' => EmailParserService::errorExcerpt($deleteError),
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
                                'error' => EmailParserService::errorExcerpt($deleteError),
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
                        'error' => EmailParserService::errorExcerpt($e),
                        'message_id' => $message->getMessageId(),
                    ]);

                    // Call progress callback even for errors
                    if ($progressCallback) {
                        $progressCallback($current, $totalMessages, null, $e);
                    }
                }

                // Free message memory after processing
                unset($message);
            }

            // Free chunk memory
            unset($messages);

            // Force garbage collection after each chunk
            gc_collect_cycles();

            $offset += $currentChunkSize;

            Log::debug('Completed email chunk', [
                'account' => $this->currentAccount->name,
                'processed' => $current,
                'total' => $totalMessages,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2).'MB',
            ]);
        }

        Log::info('Completed email fetch from IMAP', [
            'account' => $this->currentAccount->name,
            'archived_count' => count($archived),
            'total_size' => $totalSize,
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2).'MB',
        ]);

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
                'error' => EmailParserService::errorExcerpt($e),
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
