<?php

namespace App\Imports;

use App\Models\CalendarImportRun;
use App\Support\CalendarImportSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Runs one federation importer and records the outcome, so staff can see
 * on the admin page and at login when a source fails or goes quiet.
 */
class CalendarImportRunner
{
    public function run(string $key): CalendarImportRun
    {
        $source = CalendarImportSettings::source($key)
            ?? throw new InvalidArgumentException("No importer for calendar source [{$key}].");

        try {
            $result = app($source['importer'])->import();
        } catch (Throwable $exception) {
            report($exception);

            return CalendarImportRun::query()->create([
                'source' => $key,
                'error' => Str::limit($exception->getMessage(), 500),
            ]);
        }

        return CalendarImportRun::query()->create([
            'source' => $key,
            'created_count' => $result['created'],
            'updated_count' => $result['updated'],
            'skipped_count' => $result['skipped'],
        ]);
    }

    public function latestRun(string $key): ?CalendarImportRun
    {
        return CalendarImportRun::query()->where('source', $key)->latest('id')->first();
    }

    /**
     * One line per switched-on source whose latest run failed, found no
     * matches, or is older than its schedule allows.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach (CalendarImportSettings::sources() as $source) {
            $staleAfterDays = CalendarImportSettings::frequency($source['key'])->staleAfterDays();
            $run = $this->latestRun($source['key']);

            if ($staleAfterDays === null || $run === null) {
                continue;
            }

            $name = $source['name'];
            $when = $run->created_at->timezone(config('app.timezone'))->format('j M');

            if ($run->failed()) {
                $problems[] = "{$name}: import failed on {$when}. {$run->error}";
            } elseif ($run->matchesFound() === 0) {
                $problems[] = "{$name}: import on {$when} found no matches. Their site may have changed.";
            } elseif ($run->created_at->lt(now()->subDays($staleAfterDays))) {
                $problems[] = "{$name}: last import was {$when}. Check the scheduler is running.";
            }
        }

        return $problems;
    }
}
