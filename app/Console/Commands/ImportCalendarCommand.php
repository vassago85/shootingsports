<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportCalendarCommand extends Command
{
    protected $signature = 'calendar:import {source? : Source key from config/calendar_sources.php} {--list : Show scrape vs sell}';

    protected $description = 'Import matches from federation sites that publish a structured calendar, or list which bodies should be sold the embed.';

    public function handle(): int
    {
        $sources = collect(config('calendar_sources'));

        if ($this->option('list') || $this->argument('source') === null) {
            $this->table(
                ['Key', 'Mode', 'Name', 'Notes'],
                $sources->map(fn (array $source): array => [
                    $source['key'],
                    $source['mode'],
                    $source['name'],
                    $source['notes'],
                ])->all(),
            );

            if ($this->argument('source') === null && ! $this->option('list')) {
                $this->comment('Pass a source key to import, e.g. php artisan calendar:import saprf');
            }

            return self::SUCCESS;
        }

        $key = (string) $this->argument('source');
        $source = $sources->firstWhere('key', $key);

        if ($source === null) {
            $this->error('Unknown source. Use --list.');

            return self::FAILURE;
        }

        if (($source['mode'] ?? '') !== 'import' || ! isset($source['importer'])) {
            $this->warn($source['name'].' has no structured feed. Sell them the calendar embed.');

            return self::SUCCESS;
        }

        $result = app($source['importer'])->import();

        $this->info(sprintf(
            '%s: %d created, %d updated, %d staff rows left alone.',
            $source['name'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
