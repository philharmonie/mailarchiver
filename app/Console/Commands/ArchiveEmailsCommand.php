<?php

namespace App\Console\Commands;

use App\Models\ImapAccount;
use App\Services\ImapService;
use Illuminate\Console\Command;

class ArchiveEmailsCommand extends Command
{
    protected $signature = 'emails:archive
                            {--account= : Specific account ID to fetch from}
                            {--limit= : Maximum number of emails to fetch per account}
                            {--all : Fetch all emails, not just unseen ones}
                            {--test : Test connection and show folder info}';

    protected $description = 'Fetch and archive emails from IMAP mailbox(es)';

    public function __construct(protected ImapService $imapService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $accountId = $this->option('account');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($accountId) {
            $account = ImapAccount::find($accountId);

            if (! $account) {
                $this->error("Account with ID {$accountId} not found.");

                return self::FAILURE;
            }

            return $this->processAccount($account, $limit);
        }

        // Process all active accounts
        $accounts = ImapAccount::where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active IMAP accounts found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Processing %d active account(s)...', $accounts->count()));

        $totalArchived = 0;

        foreach ($accounts as $account) {
            $result = $this->processAccount($account, $limit);

            if ($result === self::FAILURE) {
                $this->warn("Skipping account '{$account->name}' due to errors.");

                continue;
            }

            $totalArchived += $result;
        }

        $this->info(sprintf('Total emails archived: %d', $totalArchived));

        return self::SUCCESS;
    }

    protected function processAccount(ImapAccount $account, ?int $limit): int
    {
        $this->info("Processing account: {$account->name}");

        try {
            $this->imapService->connectToAccount($account);

            // Test mode: show connection info
            if ($this->option('test')) {
                $this->line('  ✓ Connection successful');
                $folders = $this->imapService->getFolders();
                $this->line(sprintf('  Available folders: %s', implode(', ', $folders)));
                $this->line(sprintf('  Target folder: %s', $account->folder));

                // Get email count in folder
                $client = $this->imapService->getClient();
                $folder = $client->getFolder($account->folder);
                $total = $folder->query()->whereAll()->count();
                $unseen = $folder->query()->unseen()->count();

                $this->line(sprintf('  Total emails in folder: %d', $total));
                $this->line(sprintf('  Unseen emails: %d', $unseen));

                return 0;
            }

            $fetchAll = $this->option('all');
            $archivedCount = 0;

            // Require limit when using --all to prevent hanging on large mailboxes
            if ($fetchAll && ! $limit) {
                $this->warn('  Using --all without --limit can be very slow for large mailboxes.');
                $this->warn('  Applying default limit of 100 emails.');
                $limit = 100;
            }

            $this->line('  Fetching email list from server...');

            // Progress callback with counter display on first call
            $firstCall = true;
            $progressCallback = function ($current, $total, $email, $error = null) use (&$archivedCount, &$firstCall) {
                if ($firstCall) {
                    $this->line(sprintf('  Found %d email(s) to process', $total));
                    $firstCall = false;
                }

                if ($error) {
                    $this->error(sprintf('  [%d/%d] Failed: %s', $current, $total, $error->getMessage()));
                } else {
                    $archivedCount++;
                    $subject = mb_strlen($email->subject) > 50
                        ? mb_substr($email->subject, 0, 47).'...'
                        : $email->subject;
                    $this->line(sprintf('  [%d/%d] ✓ %s', $current, $total, $subject));
                }
            };

            $archived = $this->imapService->fetchAndArchiveEmails($limit, $fetchAll, $progressCallback);

            if (empty($archived)) {
                $this->line('  No new emails.');

                return 0;
            }

            $this->info(sprintf('  Total archived: %d email(s)', $archivedCount));

            return $archivedCount;
        } catch (\Exception $e) {
            $this->error("  Failed: {$e->getMessage()}");
            $this->error("  Stack trace: {$e->getTraceAsString()}");

            return self::FAILURE;
        }
    }
}
