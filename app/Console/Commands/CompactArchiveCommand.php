<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\Email;
use App\Services\CompressionService;
use App\Services\EmailParserService;
use App\Services\RawEmailStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves mail archived before the raw parts landed onto the new storage: the
 * attachment bytes leave `raw_email` and the attachment files that held the
 * same bytes a second time go with them.
 *
 * Every mail is checked before anything is thrown away - the mail has to come
 * back out of the parts byte for byte and match the hash it was archived
 * with. A mail that fails that is left exactly as it was.
 */
class CompactArchiveCommand extends Command
{
    protected $signature = 'archive:compact
                            {--limit= : Stop after this many mails}
                            {--dry-run : Report what would move, write nothing}';

    protected $description = 'Store attachment bytes once instead of twice';

    public function __construct(
        protected RawEmailStore $rawParts,
        protected CompressionService $compression
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Every mail is held whole for as long as it takes to take it apart,
        // and an archived mail with attachments runs to megabytes. The default
        // 128M ceiling is gone after a handful of them - same reason the sync
        // raises it, so it reads the same setting.
        ini_set('memory_limit', (string) config('monitoring.sync_memory_limit'));

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Email::whereNull('raw_parts')->orderBy('id');
        $total = $limit ? min($limit, (clone $query)->count()) : (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing left to compact.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%s %d mail%s', $dryRun ? 'Examining' : 'Compacting', $total, $total === 1 ? '' : 's'));

        $bar = $this->output->createProgressBar($total);
        $done = 0;
        $split = 0;
        $skipped = 0;
        $freed = 0;

        // Small chunks, and every mail let go of before the next: what is held
        // here is whole mail bodies, not rows.
        $query->chunkById(25, function ($emails) use (&$done, &$split, &$skipped, &$freed, $bar, $dryRun, $limit) {
            foreach ($emails as $email) {
                if ($limit !== null && $done >= $limit) {
                    return false;
                }

                try {
                    $freed += $this->compact($email, $dryRun, $split);
                } catch (\Throwable $e) {
                    $skipped++;

                    $this->newLine();
                    $this->warn("  mail {$email->id} left alone: ".EmailParserService::errorExcerpt($e));

                    Log::warning('Could not compact an archived mail', [
                        'email_id' => $email->id,
                        'error' => EmailParserService::errorExcerpt($e),
                    ]);
                }

                $done++;
                $bar->advance();

                unset($email);
            }

            gc_collect_cycles();

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%s: %d mail%s split, %d left whole, %d skipped, %s %s',
            $dryRun ? 'Would have compacted' : 'Compacted',
            $split,
            $split === 1 ? '' : 's',
            $done - $split - $skipped,
            $skipped,
            $dryRun ? 'about' : 'freeing',
            $this->humanBytes($freed)
        ));

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return int bytes that stop being stored twice
     */
    protected function compact(Email $email, bool $dryRun, int &$split): int
    {
        $raw = $email->getRawEmailDecompressed();

        if (Email::generateHash($raw) !== $email->hash) {
            throw new \RuntimeException('stored mail does not match its own hash, so it is not touched');
        }

        $parts = $this->rawParts->split($raw);

        if ($parts['hashes'] === []) {
            if (! $dryRun) {
                // Looked at, nothing worth lifting out - recorded so the next
                // run walks past it.
                $email->forceFill(['raw_parts' => []])->saveQuietly();
            }

            return 0;
        }

        // Checked against the parts in hand, not against the archive - nothing
        // is stored yet, and nothing will be if this does not hold.
        if (! $this->wouldAssemble($parts, $raw)) {
            throw new \RuntimeException('mail would not come back byte for byte');
        }

        $split++;

        if ($dryRun) {
            return $this->attachmentBytes($email, $parts['decoded_hashes']);
        }

        $freed = 0;

        DB::transaction(function () use ($email, $parts, &$freed) {
            $this->rawParts->persist($parts);

            $compress = $this->compression->shouldCompress(strlen($parts['skeleton']));

            $email->forceFill([
                'raw_email' => $compress ? $this->compression->compress($parts['skeleton']) : $parts['skeleton'],
                'raw_parts' => $parts['hashes'],
                'is_compressed' => $compress,
            ])->saveQuietly();

            $freed = $this->repointAttachments($email, $parts['decoded_hashes']);
        });

        if (! $email->fresh()->verifyHashWithDecompression()) {
            throw new \RuntimeException('mail did not verify after being compacted');
        }

        return $freed;
    }

    /**
     * Whether the parts put the mail back together exactly - checked against
     * the bytes in hand, because none of them are stored at this point.
     */
    protected function wouldAssemble(array $parts, string $raw): bool
    {
        $rebuilt = $parts['skeleton'];

        foreach ($parts['encoded'] as $hash => $bytes) {
            $rebuilt = str_replace(RawEmailStore::markerFor($hash), $bytes, $rebuilt);
        }

        return $rebuilt === $raw;
    }

    /**
     * Point every attachment whose bytes are now in a part at that part, and
     * take away the file it no longer needs.
     *
     * @param  array<string, string>  $decodedHashes  part hash => sha256 of its contents
     * @return int bytes freed
     */
    protected function repointAttachments(Email $email, array $decodedHashes): int
    {
        $freed = 0;

        foreach ($email->attachments()->whereNull('raw_part_hash')->get() as $attachment) {
            $partHash = array_search($attachment->hash, $decodedHashes, strict: true);

            if ($partHash === false) {
                continue;
            }

            $path = $attachment->storage_path;
            $disk = $attachment->storage_disk;

            $attachment->forceFill([
                'raw_part_hash' => $partHash,
                'raw_part_encoding' => 'base64',
                'storage_path' => null,
            ])->saveQuietly();

            if ($path === null) {
                continue;
            }

            // The same file backs every attachment row with these bytes, so it
            // only goes once the last of them has let go.
            $stillUsed = Attachment::where('storage_path', $path)
                ->whereNull('raw_part_hash')
                ->exists();

            if ($stillUsed || ! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $freed += Storage::disk($disk)->size($path);
            Storage::disk($disk)->delete($path);
        }

        return $freed;
    }

    /**
     * @param  array<string, string>  $decodedHashes
     */
    protected function attachmentBytes(Email $email, array $decodedHashes): int
    {
        $bytes = 0;

        foreach ($email->attachments()->whereNull('raw_part_hash')->get() as $attachment) {
            if (array_search($attachment->hash, $decodedHashes, strict: true) !== false) {
                $bytes += (int) $attachment->size_bytes;
            }
        }

        return $bytes;
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 1).' GB';
    }
}
