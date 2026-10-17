<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * One-off (and safe to re-run) normaliser for all-day events whose
 * stored starts_at/ends_at is not at local midnight in SAST.
 *
 * Historically the SAPRF importer stored dates as "YYYY-MM-DD 00:00:00"
 * in UTC, which Africa/Johannesburg renders as 02:00. The fix is to
 * re-anchor to local midnight so no public card, admin table, or iCal
 * feed shows a phantom 02:00 time for a date-only event.
 */
class FixEventAllDayTimes extends Command
{
    protected $signature = 'events:fix-all-day-times {--dry-run : Report what would change without writing}';

    protected $description = 'Normalise all-day events whose starts_at or ends_at is not at local midnight';

    public function handle(): int
    {
        $timezone = (string) config('app.timezone');
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $scanned = 0;

        Event::query()
            ->where('all_day', true)
            ->whereNotNull('starts_at')
            ->chunkById(200, function ($events) use ($timezone, $dryRun, &$fixed, &$scanned): void {
                foreach ($events as $event) {
                    $scanned++;

                    $start = $event->starts_at?->timezone($timezone);
                    $end = $event->ends_at?->timezone($timezone);

                    $startOff = $start !== null && ! $start->copy()->startOfDay()->equalTo($start);
                    $endOff = $end !== null && ! $end->copy()->startOfDay()->equalTo($end);

                    if (! $startOff && ! $endOff) {
                        continue;
                    }

                    $fixed++;

                    $this->line(sprintf(
                        '%s #%d "%s" starts=%s ends=%s',
                        $dryRun ? '[dry-run]' : '[fix]',
                        $event->id,
                        $event->title,
                        $start?->toDateTimeString() ?? '-',
                        $end?->toDateTimeString() ?? '-',
                    ));

                    if ($dryRun) {
                        continue;
                    }

                    $event->forceFill([
                        'starts_at' => $start?->copy()->startOfDay(),
                        'ends_at' => $end?->copy()->startOfDay(),
                    ])->save();
                }
            });

        $this->info(sprintf(
            '%sScanned %d all-day events; %s %d.',
            $dryRun ? '[dry-run] ' : '',
            $scanned,
            $dryRun ? 'would fix' : 'fixed',
            $fixed,
        ));

        return self::SUCCESS;
    }
}
