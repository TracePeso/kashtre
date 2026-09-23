<?php

namespace App\Console\Commands;

use App\Support\DemoWorkbook\DemoWorkbookImporter;
use Illuminate\Console\Command;

class ImportDemoWorkbookCommand extends Command
{
    protected $signature = 'kashtre:import-demo-workbook
        {path? : Path to Kashtre Import Data.xlsx}
        {--only= : Comma-separated entity codes (MCC,LSH,CCTH)}';

    protected $description = 'Import the three demonstration organisations from Kashtre Import Data.xlsx';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('data/Kashtre Import Data.xlsx');

        if (! is_file($path)) {
            $this->error('Workbook not found: '.$path);

            return self::FAILURE;
        }

        $only = collect(explode(',', (string) $this->option('only')))
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->filter()
            ->values()
            ->all();

        $this->info('Importing demonstration organisations from '.$path);
        if ($only !== []) {
            $this->info('Limited to: '.implode(', ', $only));
        }

        $summary = (new DemoWorkbookImporter($path, $this->output, $only))->import();

        $this->newLine();
        $this->info('Import finished.');
        foreach ($summary as $line) {
            $this->line('  '.$line);
        }
        $this->newLine();
        $this->comment('Demo users sign in with password "password". 2FA is off for these organisations.');

        return self::SUCCESS;
    }
}
