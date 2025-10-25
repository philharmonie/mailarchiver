<?php

namespace App\Services;

use App\Models\Email;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class GobdExportService
{
    public function __construct(
        protected CompressionService $compression
    ) {}

    /**
     * Export emails in GoBD-compliant format
     *
     * @param  Carbon|null  $dateFrom  Start date (inclusive)
     * @param  Carbon|null  $dateTo  End date (inclusive)
     * @param  string|null  $outputPath  Output path for ZIP file (defaults to storage/app/exports/)
     * @return array{success: bool, file: string|null, count: int, size: int, error: string|null}
     */
    public function export(?Carbon $dateFrom = null, ?Carbon $dateTo = null, ?string $outputPath = null): array
    {
        try {
            // Get emails in date range
            $emails = $this->getEmailsInDateRange($dateFrom, $dateTo);

            if ($emails->isEmpty()) {
                return [
                    'success' => false,
                    'file' => null,
                    'count' => 0,
                    'size' => 0,
                    'error' => 'No emails found in the specified date range.',
                ];
            }

            // Create temporary directory
            $tempDir = storage_path('app/temp/gobd_export_'.uniqid());
            File::ensureDirectoryExists($tempDir);
            File::ensureDirectoryExists($tempDir.'/emails');

            // Export .eml files and collect metadata
            $metadata = $this->exportEmlFiles($emails, $tempDir);

            // Generate index.xml
            $this->generateIndexXml($metadata, $tempDir);

            // Generate index.csv (alternative format)
            $this->generateIndexCsv($metadata, $tempDir);

            // Generate hashes.txt
            $this->generateHashesFile($metadata, $tempDir);

            // Generate readme.txt
            $this->generateReadme($dateFrom, $dateTo, $emails->count(), $tempDir);

            // Create ZIP archive
            $zipPath = $this->createZipArchive($tempDir, $dateFrom, $dateTo, $outputPath);

            // Cleanup temp directory
            File::deleteDirectory($tempDir);

            return [
                'success' => true,
                'file' => $zipPath,
                'count' => $emails->count(),
                'size' => File::size($zipPath),
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'file' => null,
                'count' => 0,
                'size' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get emails in date range
     */
    protected function getEmailsInDateRange(?Carbon $dateFrom, ?Carbon $dateTo): Collection
    {
        $query = Email::query()
            ->orderBy('received_at', 'asc')
            ->orderBy('id', 'asc');

        if ($dateFrom) {
            $query->where('received_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('received_at', '<=', $dateTo);
        }

        return $query->get();
    }

    /**
     * Export .eml files and collect metadata
     */
    protected function exportEmlFiles(Collection $emails, string $tempDir): array
    {
        $metadata = [];

        foreach ($emails as $email) {
            // Get raw email (decompressed)
            $rawEmail = $email->getRawEmailDecompressed();

            // Generate file path: emails/YYYY/MM/DD/ID.eml
            $date = $email->received_at;
            $relativePath = sprintf(
                'emails/%s/%s/%s/%d.eml',
                $date->format('Y'),
                $date->format('m'),
                $date->format('d'),
                $email->id
            );

            $fullPath = $tempDir.'/'.$relativePath;

            // Ensure directory exists
            File::ensureDirectoryExists(dirname($fullPath));

            // Write .eml file
            File::put($fullPath, $rawEmail);

            // Calculate SHA256 hash
            $sha256 = hash('sha256', $rawEmail);

            // Collect metadata
            $metadata[] = [
                'id' => $email->id,
                'file' => $relativePath,
                'from' => $email->from_address,
                'from_name' => $email->from_name,
                'to' => is_array($email->to_addresses) ? implode(', ', $email->to_addresses) : '',
                'cc' => is_array($email->cc_addresses) ? implode(', ', $email->cc_addresses) : '',
                'bcc' => is_array($email->bcc_addresses) ? implode(', ', $email->bcc_addresses) : '',
                'subject' => $email->subject,
                'date' => $email->received_at->toIso8601String(),
                'archived_at' => $email->archived_at?->toIso8601String(),
                'size_bytes' => $email->size_bytes,
                'has_attachments' => $email->has_attachments ? 'yes' : 'no',
                'sha256' => $sha256,
                'hash_verified' => $email->verifyHashWithDecompression() ? 'yes' : 'no',
                'bcc_map_type' => $email->bcc_map_type ?? '',
            ];
        }

        return $metadata;
    }

    /**
     * Generate index.xml
     */
    protected function generateIndexXml(array $metadata, string $tempDir): void
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><MailArchiveExport/>');
        $xml->addAttribute('version', '1.0');
        $xml->addAttribute('created', now()->toIso8601String());
        $xml->addAttribute('application', 'MailArchive');
        $xml->addAttribute('format', 'GoBD-compliant');

        foreach ($metadata as $data) {
            $mail = $xml->addChild('Mail');
            foreach ($data as $key => $value) {
                $mail->addChild($key, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
            }
        }

        File::put($tempDir.'/index.xml', $xml->asXML());
    }

    /**
     * Generate index.csv
     */
    protected function generateIndexCsv(array $metadata, string $tempDir): void
    {
        $fp = fopen($tempDir.'/index.csv', 'w');

        // Write BOM for Excel compatibility
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write header
        if (! empty($metadata)) {
            fputcsv($fp, array_keys($metadata[0]));
        }

        // Write rows
        foreach ($metadata as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    /**
     * Generate hashes.txt
     */
    protected function generateHashesFile(array $metadata, string $tempDir): void
    {
        $content = "GoBD Export - Email Integrity Hashes (SHA256)\n";
        $content .= 'Generated: '.now()->toIso8601String()."\n";
        $content .= "Format: <hash> <file>\n";
        $content .= str_repeat('=', 80)."\n\n";

        foreach ($metadata as $data) {
            $content .= sprintf("%s  %s\n", $data['sha256'], $data['file']);
        }

        File::put($tempDir.'/hashes.txt', $content);
    }

    /**
     * Generate readme.txt
     */
    protected function generateReadme(?Carbon $dateFrom, ?Carbon $dateTo, int $emailCount, string $tempDir): void
    {
        $content = <<<'README'
================================================================================
GoBD-KONFORMER E-MAIL-EXPORT
================================================================================

Export-Informationen:
---------------------
Datum des Exports: %s
Zeitraum: %s bis %s
Anzahl E-Mails: %d
Software: MailArchive
Version: 1.0
Format: GoBD-konform gemäß BMF-Schreiben vom 14.11.2014

Inhalt des Archivs:
------------------
1. /emails/               - E-Mail-Dateien im .eml-Format (Original)
                           Struktur: emails/YYYY/MM/DD/ID.eml

2. index.xml             - Strukturierte Metadaten (XML-Format)
                           Enthält: ID, Absender, Empfänger, Datum,
                           Betreff, Dateipfad, SHA256-Prüfsumme

3. index.csv             - Alternative Metadaten (CSV-Format)
                           Für Excel/Spreadsheet-Programme

4. hashes.txt            - SHA256-Prüfsummen aller E-Mails
                           Format: <hash> <datei>
                           Zur Integritätsprüfung

5. readme.txt            - Diese Datei

GoBD-Anforderungen:
------------------
✓ Vollständigkeit        - Alle E-Mails des Zeitraums
✓ Unveränderbarkeit      - SHA256-Prüfsummen zur Verifikation
✓ Nachvollziehbarkeit    - Zeitliche Sortierung, vollständige Metadaten
✓ Maschinelle Auswertung - XML/CSV-Index, Standard .eml-Format
✓ Lesbarkeit             - .eml-Dateien mit jedem E-Mail-Client lesbar

Prüfsummen-Verifikation:
-----------------------
Linux/Mac:
  sha256sum -c hashes.txt

Windows (PowerShell):
  Get-FileHash -Algorithm SHA256 <datei> | Compare-Object (Get-Content hashes.txt)

Metadaten-Struktur (XML):
-------------------------
<Mail>
  <id>            - Eindeutige Datensatz-ID
  <file>          - Relativer Pfad zur .eml-Datei
  <from>          - Absender-E-Mail-Adresse
  <from_name>     - Absender-Name
  <to>            - Empfänger (kommagetrennt)
  <cc>            - CC-Empfänger (kommagetrennt)
  <bcc>           - BCC-Empfänger (kommagetrennt)
  <subject>       - E-Mail-Betreff
  <date>          - Empfangsdatum (ISO 8601)
  <archived_at>   - Archivierungsdatum (ISO 8601)
  <size_bytes>    - Größe in Bytes
  <has_attachments> - Anhänge vorhanden (yes/no)
  <sha256>        - SHA256-Prüfsumme
  <hash_verified> - Hash-Verifikation (yes/no)
  <bcc_map_type>  - Zuordnung (sender/recipient/both)
</Mail>

Rechtliche Grundlagen:
---------------------
- BMF-Schreiben vom 14.11.2014 (GoBD)
- § 147 AO (Aufbewahrungspflichten)
- § 257 HGB (Aufbewahrungspflichten)

Aufbewahrungsfrist:
------------------
E-Mails mit steuerlicher Relevanz: 6-10 Jahre

Technischer Support:
-------------------
Bei Fragen zur Datenstruktur oder Verifikation wenden Sie sich bitte
an den System-Administrator oder die verantwortliche IT-Abteilung.

================================================================================
README;

        $content = sprintf(
            $content,
            now()->format('d.m.Y H:i:s'),
            $dateFrom ? $dateFrom->format('d.m.Y') : 'Beginn',
            $dateTo ? $dateTo->format('d.m.Y') : 'Heute',
            $emailCount
        );

        File::put($tempDir.'/readme.txt', $content);
    }

    /**
     * Create ZIP archive
     */
    protected function createZipArchive(string $tempDir, ?Carbon $dateFrom, ?Carbon $dateTo, ?string $outputPath): string
    {
        // Determine output path
        if (! $outputPath) {
            $exportDir = storage_path('app/exports');
            File::ensureDirectoryExists($exportDir);

            $filename = sprintf(
                'GoBD_Export_%s_%s.zip',
                $dateFrom ? $dateFrom->format('Y-m-d') : 'all',
                $dateTo ? $dateTo->format('Y-m-d') : now()->format('Y-m-d')
            );

            $outputPath = $exportDir.'/'.$filename;
        }

        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add all files from temp directory
            $files = File::allFiles($tempDir);

            foreach ($files as $file) {
                $relativePath = str_replace($tempDir.'/', '', $file->getPathname());
                $zip->addFile($file->getPathname(), $relativePath);
            }

            // Add readme and other root files
            $rootFiles = ['readme.txt', 'index.xml', 'index.csv', 'hashes.txt'];
            foreach ($rootFiles as $rootFile) {
                if (File::exists($tempDir.'/'.$rootFile)) {
                    $zip->addFile($tempDir.'/'.$rootFile, $rootFile);
                }
            }

            $zip->close();
        }

        return $outputPath;
    }
}
