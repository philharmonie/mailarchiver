<?php

namespace App\Console\Commands;

use App\Services\GobdExportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportGobdCommand extends Command
{
    protected $signature = 'emails:export-gobd
                            {--from= : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD)}
                            {--output= : Output path for ZIP file}
                            {--year= : Export specific year (e.g. 2024)}';

    protected $description = 'Export emails in GoBD-compliant format for tax audits';

    public function __construct(protected GobdExportService $exportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting GoBD-compliant email export...');

        // Parse date range
        $dateFrom = $this->parseDateFrom();
        $dateTo = $this->parseDateTo();

        if ($dateFrom && $dateTo && $dateFrom->isAfter($dateTo)) {
            $this->error('Start date must be before end date.');

            return self::FAILURE;
        }

        // Display export parameters
        $this->line(sprintf(
            'Export period: %s to %s',
            $dateFrom ? $dateFrom->format('d.m.Y') : 'Beginning',
            $dateTo ? $dateTo->format('d.m.Y') : 'Today'
        ));

        // Confirm export
        if (! $this->confirm('Proceed with export?', true)) {
            $this->info('Export cancelled.');

            return self::SUCCESS;
        }

        $this->newLine();

        // Perform export
        $result = $this->exportService->export(
            $dateFrom,
            $dateTo,
            $this->option('output')
        );

        // Display results
        if ($result['success']) {
            $this->info('✓ Export completed successfully!');
            $this->newLine();
            $this->line(sprintf('Emails exported: %d', $result['count']));
            $this->line(sprintf('Archive size: %s', $this->formatBytes($result['size'])));
            $this->line(sprintf('File location: %s', $result['file']));
            $this->newLine();
            $this->comment('The export contains:');
            $this->line('  - /emails/ - All emails as .eml files');
            $this->line('  - index.xml - Structured metadata (XML)');
            $this->line('  - index.csv - Alternative metadata (CSV)');
            $this->line('  - hashes.txt - SHA256 checksums for integrity verification');
            $this->line('  - readme.txt - Documentation and verification instructions');
            $this->newLine();
            $this->info('This export is GoBD-compliant and ready for tax audits.');

            return self::SUCCESS;
        } else {
            $this->error('✗ Export failed: '.$result['error']);

            return self::FAILURE;
        }
    }

    protected function parseDateFrom(): ?Carbon
    {
        // --year takes precedence
        if ($this->option('year')) {
            $year = (int) $this->option('year');

            return Carbon::create($year, 1, 1)->startOfDay();
        }

        if ($this->option('from')) {
            try {
                return Carbon::parse($this->option('from'))->startOfDay();
            } catch (\Exception $e) {
                $this->error('Invalid start date format. Use YYYY-MM-DD.');
                exit(1);
            }
        }

        return null;
    }

    protected function parseDateTo(): ?Carbon
    {
        // --year takes precedence
        if ($this->option('year')) {
            $year = (int) $this->option('year');

            return Carbon::create($year, 12, 31)->endOfDay();
        }

        if ($this->option('to')) {
            try {
                return Carbon::parse($this->option('to'))->endOfDay();
            } catch (\Exception $e) {
                $this->error('Invalid end date format. Use YYYY-MM-DD.');
                exit(1);
            }
        }

        return null;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
