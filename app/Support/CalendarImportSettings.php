<?php

namespace App\Support;

use App\Enums\ImportFrequency;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * How often each federation calendar importer runs. Staff change it on
 * the Calendar imports admin page; sources default to weekly.
 */
final class CalendarImportSettings
{
    /**
     * Sources from config/calendar_sources.php that have a working importer.
     *
     * @return list<array{key: string, name: string, url: string, mode: string, importer: class-string, notes: string}>
     */
    public static function sources(): array
    {
        return array_values(array_filter(
            config('calendar_sources'),
            fn (array $source): bool => ($source['mode'] ?? '') === 'import' && isset($source['importer']),
        ));
    }

    /**
     * @return array{key: string, name: string, url: string, mode: string, importer: class-string, notes: string}|null
     */
    public static function source(string $key): ?array
    {
        foreach (self::sources() as $source) {
            if ($source['key'] === $key) {
                return $source;
            }
        }

        return null;
    }

    public static function frequency(string $key): ImportFrequency
    {
        return ImportFrequency::tryFrom((string) Setting::get("calendar_import.{$key}.frequency"))
            ?? ImportFrequency::Weekly;
    }

    public static function setFrequency(string $key, ImportFrequency $frequency): void
    {
        Setting::put("calendar_import.{$key}.frequency", $frequency->value);
    }

    public static function isDueOn(string $key, CarbonInterface $day): bool
    {
        return self::frequency($key)->isDueOn($day);
    }

    /**
     * Sources start 20 minutes apart from 06:00 so they never overlap.
     */
    public static function runTime(int $position): string
    {
        return now()->setTime(6, 0)->addMinutes(20 * $position)->format('H:i');
    }
}
