<?php

namespace App\Services;

class CompressionService
{
    public function compress(string $data): string
    {
        $compressed = gzencode($data, 9); // Maximum compression level

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress data');
        }

        return $compressed;
    }

    public function decompress(string $compressedData): string
    {
        $decompressed = gzdecode($compressedData);

        if ($decompressed === false) {
            throw new \RuntimeException('Failed to decompress data');
        }

        return $decompressed;
    }

    public function shouldCompress(int $dataSize, int $threshold = 1024): bool
    {
        return $dataSize >= $threshold;
    }

    public function getCompressionRatio(string $original, string $compressed): float
    {
        $originalSize = strlen($original);
        $compressedSize = strlen($compressed);

        if ($originalSize === 0) {
            return 0.0;
        }

        return round((1 - ($compressedSize / $originalSize)) * 100, 2);
    }
}
