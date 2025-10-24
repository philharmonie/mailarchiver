<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TextExtractorService
{
    protected bool $pdfToTextAvailable;

    public function __construct()
    {
        // Check if pdftotext is available on the system
        $this->pdfToTextAvailable = $this->checkPdfToText();
    }

    /**
     * Extract text from a file based on its MIME type
     */
    public function extractText(string $filePath, string $mimeType): ?string
    {
        return match (true) {
            str_starts_with($mimeType, 'application/pdf') => $this->extractFromPdf($filePath),
            str_starts_with($mimeType, 'text/') => $this->extractFromText($filePath),
            default => null,
        };
    }

    /**
     * Extract text from PDF using pdftotext command
     */
    protected function extractFromPdf(string $filePath): ?string
    {
        if (! $this->pdfToTextAvailable) {
            Log::debug('pdftotext not available, skipping PDF text extraction');

            return null;
        }

        if (! file_exists($filePath)) {
            return null;
        }

        try {
            // Use pdftotext to extract text
            $outputPath = $filePath.'.txt';
            $command = sprintf(
                'pdftotext %s %s 2>&1',
                escapeshellarg($filePath),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($outputPath)) {
                $text = file_get_contents($outputPath);
                unlink($outputPath); // Clean up temp file

                return $text ?: null;
            }

            Log::debug('pdftotext extraction failed', [
                'return_code' => $returnCode,
                'output' => $output,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('PDF text extraction error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract text from plain text files
     */
    protected function extractFromText(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        try {
            // Limit to first 50KB to avoid memory issues
            $text = file_get_contents($filePath, false, null, 0, 50 * 1024);

            return $text ?: null;
        } catch (\Exception $e) {
            Log::error('Text file extraction error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if pdftotext command is available
     */
    protected function checkPdfToText(): bool
    {
        exec('which pdftotext 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if text extraction is available for a given MIME type
     */
    public function canExtract(string $mimeType): bool
    {
        return match (true) {
            str_starts_with($mimeType, 'application/pdf') => $this->pdfToTextAvailable,
            str_starts_with($mimeType, 'text/') => true,
            default => false,
        };
    }
}
