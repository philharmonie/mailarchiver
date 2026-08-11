<?php

namespace App\Console\Commands;

use App\Models\ImapAccount;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The heartbeat used to hang off the exit code of `imap:sync`, and an exit
 * code is the wrong question. A run where nothing was due archives nothing
 * and still succeeds, so the ping kept saying "OK" for an archive that had
 * not moved in hours - which is exactly how a four hour gap went unseen while
 * every check reported healthy.
 *
 * What the monitor is meant to answer is whether the archive is current, and
 * that is written down: last_sync_at per account. This command reads it and
 * pings only while every active account is inside the stale threshold.
 */
class MonitoringHeartbeatCommand extends Command
{
    protected $signature = 'monitoring:heartbeat';

    protected $description = 'Ping the heartbeat URL while every active account is synced within the stale threshold';

    public function handle(): int
    {
        $threshold = (int) config('monitoring.stale_threshold_minutes');

        $accounts = ImapAccount::query()
            ->where('is_active', true)
            ->whereNotNull('sync_interval')
            ->orderBy('name')
            ->get(['id', 'name', 'last_sync_at']);

        if ($reason = $this->staleReason($accounts, $threshold)) {
            $this->error($reason);
            Log::warning('Heartbeat withheld', ['reason' => $reason]);

            // Only the failure URL is pinged here. Saying nothing would also
            // trip the monitor once its own interval runs out; saying it now
            // makes the archive go red at the same moment it went stale.
            $this->ping(config('monitoring.heartbeat_url_fail'));

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d account(s) synced within the last %d minutes',
            $accounts->count(),
            $threshold,
        ));

        $this->ping(config('monitoring.heartbeat_url'));

        return self::SUCCESS;
    }

    /**
     * Why the archive must not be called current, or null while it is.
     *
     * @param  Collection<int, ImapAccount>  $accounts
     */
    protected function staleReason(Collection $accounts, int $threshold): ?string
    {
        // An archive nobody syncs is not a healthy archive, it is one whose
        // accounts were all switched off. Inactive accounts are skipped
        // everywhere else on purpose - deactivating one is a decision, not a
        // fault - but none left at all is worth a red monitor.
        if ($accounts->isEmpty()) {
            return 'No active account has a sync interval - nothing is being archived';
        }

        $deadline = now()->subMinutes($threshold);

        $stale = $accounts
            ->filter(fn (ImapAccount $account) => $account->last_sync_at === null
                || $account->last_sync_at->lessThan($deadline))
            ->map(fn (ImapAccount $account) => $account->last_sync_at === null
                ? "{$account->name} (never synced)"
                : sprintf(
                    '%s (%d min ago)',
                    $account->name,
                    (int) $account->last_sync_at->diffInMinutes(now()),
                ));

        if ($stale->isEmpty()) {
            return null;
        }

        return sprintf(
            'Stale beyond %d minutes: %s',
            $threshold,
            $stale->implode(', '),
        );
    }

    /**
     * A heartbeat that cannot be delivered is a problem of the monitoring
     * path, not of the archive. It is logged, and the monitor notices the
     * silence by itself - letting it throw would turn every hiccup on the way
     * to the check into a failed scheduler run and a mail about it.
     */
    protected function ping(?string $url): void
    {
        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)->get($url);
        } catch (\Throwable $e) {
            Log::warning('Heartbeat ping failed', ['error' => $e->getMessage()]);
        }
    }
}
