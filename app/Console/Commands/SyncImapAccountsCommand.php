<?php

namespace App\Console\Commands;

use App\Models\ImapAccount;
use App\Services\ImapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncImapAccountsCommand extends Command
{
    protected $signature = 'imap:sync
                            {--interval= : Only sync accounts with this specific interval}';

    protected $description = 'Automatically sync IMAP accounts based on their configured intervals';

    public function __construct(protected ImapService $imapService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $specificInterval = $this->option('interval');

        // Get accounts that need syncing
        $accounts = $this->getAccountsToSync($specificInterval);

        if ($accounts->isEmpty()) {
            $this->info('No accounts need syncing at this time.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d account(s) to sync', $accounts->count()));

        $successCount = 0;
        $failureCount = 0;

        foreach ($accounts as $account) {
            $this->line("Processing: {$account->name}");

            try {
                $result = $this->syncAccount($account);

                if ($result) {
                    $successCount++;
                    $this->info('  ✓ Synced successfully');
                } else {
                    $failureCount++;
                    $this->warn('  ⊝ No new emails');
                }

                // Update last_sync_at timestamp
                $account->update(['last_sync_at' => now()]);
            } catch (\Exception $e) {
                $failureCount++;
                $this->error("  ✗ Failed: {$e->getMessage()}");

                Log::error('Auto-sync failed for account', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Sync complete: %d successful, %d failed',
            $successCount,
            $failureCount
        ));

        return self::SUCCESS;
    }

    protected function getAccountsToSync(?string $specificInterval): \Illuminate\Database\Eloquent\Collection
    {
        $query = ImapAccount::where('is_active', true)
            ->whereNotNull('sync_interval');

        if ($specificInterval) {
            $query->where('sync_interval', $specificInterval);
        } else {
            // Only get accounts that are due for sync
            $query->where(function ($q) {
                $now = now();

                // Every 15 minutes
                $q->orWhere(function ($subQ) use ($now) {
                    $subQ->where('sync_interval', 'every_15_minutes')
                        ->where(function ($lastSyncQ) use ($now) {
                            $lastSyncQ->whereNull('last_sync_at')
                                ->orWhere('last_sync_at', '<=', $now->copy()->subMinutes(15));
                        });
                });

                // Hourly
                $q->orWhere(function ($subQ) use ($now) {
                    $subQ->where('sync_interval', 'hourly')
                        ->where(function ($lastSyncQ) use ($now) {
                            $lastSyncQ->whereNull('last_sync_at')
                                ->orWhere('last_sync_at', '<=', $now->copy()->subHour());
                        });
                });

                // Every 6 hours
                $q->orWhere(function ($subQ) use ($now) {
                    $subQ->where('sync_interval', 'every_6_hours')
                        ->where(function ($lastSyncQ) use ($now) {
                            $lastSyncQ->whereNull('last_sync_at')
                                ->orWhere('last_sync_at', '<=', $now->copy()->subHours(6));
                        });
                });

                // Daily
                $q->orWhere(function ($subQ) use ($now) {
                    $subQ->where('sync_interval', 'daily')
                        ->where(function ($lastSyncQ) use ($now) {
                            $lastSyncQ->whereNull('last_sync_at')
                                ->orWhere('last_sync_at', '<=', $now->copy()->subDay());
                        });
                });

                // Weekly
                $q->orWhere(function ($subQ) use ($now) {
                    $subQ->where('sync_interval', 'weekly')
                        ->where(function ($lastSyncQ) use ($now) {
                            $lastSyncQ->whereNull('last_sync_at')
                                ->orWhere('last_sync_at', '<=', $now->copy()->subWeek());
                        });
                });
            });
        }

        return $query->get();
    }

    protected function syncAccount(ImapAccount $account): bool
    {
        $this->imapService->connectToAccount($account);

        $archived = $this->imapService->fetchAndArchiveEmails();

        return ! empty($archived);
    }
}
