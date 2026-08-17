<?php

namespace App\Services;

use App\Models\RawPart;
use Illuminate\Support\Facades\Storage;

/**
 * Splits the attachments out of a raw mail and puts them back.
 *
 * An attachment travels through a mail as base64: long runs of fixed-width
 * lines, and in a mail with attachments that is nearly all of it - 665 KB
 * against the 9 KB of a mail without. The archive stored those bytes twice,
 * once here and once as the decoded attachment file.
 *
 * So the runs are lifted out into raw parts, addressed by the hash of the
 * encoded bytes, and what stays behind is a marker. Two things depend on
 * getting this exactly right:
 *
 *   - the mail has to come back byte for byte, because the GoBD hash was taken
 *     over the mail as it arrived. That is why the run is stored as it was
 *     encoded, and never decoded and encoded again - re-encoding depends on
 *     line width, padding and line endings agreeing with whatever wrote the
 *     mail, and one wrong byte fails the integrity check.
 *   - the attachment has to be servable from the part alone, which it is:
 *     base64 decodes without knowing anything about the mail around it.
 */
class RawEmailStore
{
    /**
     * Runs shorter than this stay in the mail. Small inline images are not
     * worth a file, a row and a lookup.
     */
    protected const MIN_PART_BYTES = 4096;

    /** A base64 body line is 76 characters at most, and 60 at least here. */
    protected const MIN_LINE_LENGTH = 60;

    protected const MARKER_PREFIX = "\0\0mailarchive:part:";

    protected const MARKER_SUFFIX = "\0\0";

    public function __construct(protected CompressionService $compression) {}

    /**
     * Take a mail apart without writing anything.
     *
     * Returns the skeleton, the hash of every part in the order they appear,
     * the encoded bytes per hash, and the sha256 of what each part decodes to
     * - which is how an attachment finds the part holding its bytes.
     *
     * @return array{skeleton: string, hashes: array<int, string>, encoded: array<string, string>, decoded_hashes: array<string, string>}
     */
    public function split(string $raw): array
    {
        $unsplit = [
            'skeleton' => $raw,
            'hashes' => [],
            'encoded' => [],
            'decoded_hashes' => [],
        ];

        // A mail that already carries something shaped like a marker is left
        // alone rather than risking a wrong reassembly.
        if (str_contains($raw, self::MARKER_PREFIX)) {
            return $unsplit;
        }

        $runs = $this->findBase64Runs($raw);

        if ($runs === []) {
            return $unsplit;
        }

        $skeleton = '';
        $offset = 0;
        $hashes = [];
        $encoded = [];
        $decodedHashes = [];

        foreach ($runs as [$start, $length]) {
            $bytes = substr($raw, $start, $length);
            $hash = hash('sha256', $bytes);

            $skeleton .= substr($raw, $offset, $start - $offset);
            $skeleton .= self::MARKER_PREFIX.$hash.self::MARKER_SUFFIX;
            $offset = $start + $length;

            $hashes[] = $hash;
            $encoded[$hash] = $bytes;
            $decodedHashes[$hash] = hash('sha256', base64_decode($bytes, strict: false));
        }

        $skeleton .= substr($raw, $offset);

        return [
            'skeleton' => $skeleton,
            'hashes' => $hashes,
            'encoded' => $encoded,
            'decoded_hashes' => $decodedHashes,
        ];
    }

    /**
     * Write the parts of a split mail, sharing whatever is already stored.
     *
     * @param  array{hashes: array<int, string>, encoded: array<string, string>}  $split
     */
    public function persist(array $split): void
    {
        foreach (array_unique($split['hashes']) as $hash) {
            $part = RawPart::where('hash', $hash)->first();

            if ($part) {
                $part->increment('reference_count');

                continue;
            }

            $bytes = $split['encoded'][$hash];
            $compress = $this->compression->shouldCompress(strlen($bytes));
            $path = self::storagePathFor($hash);

            Storage::disk('local')->put($path, $compress ? $this->compression->compress($bytes) : $bytes);

            RawPart::create([
                'hash' => $hash,
                'size_bytes' => strlen($bytes),
                'storage_path' => $path,
                'storage_disk' => 'local',
                'is_compressed' => $compress,
                'reference_count' => 1,
            ]);
        }
    }

    /**
     * Put the mail back together, exactly as it arrived.
     */
    public function assemble(string $skeleton): string
    {
        if (! str_contains($skeleton, self::MARKER_PREFIX)) {
            return $skeleton;
        }

        $pattern = '/'.preg_quote(self::MARKER_PREFIX, '/').'([0-9a-f]{64})'.preg_quote(self::MARKER_SUFFIX, '/').'/';

        return preg_replace_callback($pattern, function (array $match): string {
            $part = RawPart::where('hash', $match[1])->first();

            if (! $part) {
                throw new \RuntimeException("Raw part {$match[1]} is not in the archive");
            }

            return $part->contents();
        }, $skeleton);
    }

    /**
     * Hand back the parts a mail was holding.
     *
     * @param  array<int, string>  $hashes
     */
    public function release(array $hashes): void
    {
        foreach ($hashes as $hash) {
            RawPart::where('hash', $hash)->first()?->release();
        }
    }

    /**
     * Where the base64 runs sit in the mail, as [offset, length] over the
     * original bytes - line endings included, so a run can be cut out and
     * pasted back without touching what surrounds it.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected function findBase64Runs(string $raw): array
    {
        // Keeps the line endings on the lines, which is what makes the offsets
        // add up to the original.
        $lines = preg_split('/(?<=\n)/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $runs = [];
        $offset = 0;
        $runStart = null;
        $runLength = 0;

        foreach ($lines as $line) {
            $content = rtrim($line, "\r\n");
            $isFull = strlen($content) >= self::MIN_LINE_LENGTH && $this->isBase64($content);

            if ($isFull) {
                $runStart ??= $offset;
                $runLength += strlen($line);
                $offset += strlen($line);

                continue;
            }

            // The last line of a run is usually short and often padded, so it
            // belongs to the run that just ended rather than to what follows.
            if ($runStart !== null && $content !== '' && $this->isBase64($content)) {
                $runLength += strlen($line);
                $offset += strlen($line);
                $runs[] = [$runStart, $runLength];
                $runStart = null;
                $runLength = 0;

                continue;
            }

            if ($runStart !== null) {
                $runs[] = [$runStart, $runLength];
                $runStart = null;
                $runLength = 0;
            }

            $offset += strlen($line);
        }

        if ($runStart !== null) {
            $runs[] = [$runStart, $runLength];
        }

        return array_values(array_filter($runs, fn (array $run) => $run[1] >= self::MIN_PART_BYTES));
    }

    protected function isBase64(string $line): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $line);
    }

    /**
     * The marker a stored mail carries in place of a part - for tests and for
     * anything that has to recognise a split mail without reassembling it.
     */
    public static function markerFor(string $hash): string
    {
        return self::MARKER_PREFIX.$hash.self::MARKER_SUFFIX;
    }

    public static function looksSplit(string $skeleton): bool
    {
        return str_contains($skeleton, self::MARKER_PREFIX);
    }

    public static function storagePathFor(string $hash): string
    {
        return 'raw-parts/'.substr($hash, 0, 2).'/'.$hash;
    }
}
