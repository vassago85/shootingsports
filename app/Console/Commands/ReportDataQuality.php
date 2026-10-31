<?php

namespace App\Console\Commands;

use App\Support\DataQualityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReportDataQuality extends Command
{
    protected $signature = 'data-quality:report';

    protected $description = 'Report venue, event, and import-source data problems for an admin to fix.';

    public function handle(DataQualityReport $report): int
    {
        $findings = $report->findings();

        Cache::forever('seo.data-quality.latest', [
            'at' => now()->toAtomString(),
            'findings' => $findings,
        ]);

        if ($findings === []) {
            $this->info('No data-quality findings.');

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->warn($finding);
        }

        Log::warning('Data quality findings.', ['findings' => $findings]);

        return self::SUCCESS;
    }
}
