<?php

namespace App\Support;

use App\Jobs\PingIndexNow;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;

/**
 * Queues an IndexNow ping for a public listing URL.
 *
 * Off unless INDEXNOW is enabled. Production enables it when the env
 * flag is unset. Tests and local stay quiet so saves do not call out.
 */
class IndexNow
{
    public static function dispatchFor(Event|Organisation|Venue|Provider $record): void
    {
        if (! config('services.indexnow.enabled') || blank(config('services.indexnow.key'))) {
            return;
        }

        $urls = array_filter([
            self::url($record, $record->slug),
            self::previousUrl($record),
        ]);

        foreach (array_unique($urls) as $url) {
            PingIndexNow::dispatch($url)->afterCommit();
        }
    }

    public static function url(Event|Organisation|Venue|Provider $record, ?string $slug): ?string
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return match (true) {
            $record instanceof Event => route('events.show', $slug),
            $record instanceof Organisation => $record->isFederationListing()
                ? route('federations.show', $slug)
                : route('clubs.show', $slug),
            $record instanceof Venue => route('ranges.show', $slug),
            $record instanceof Provider => route('suppliers.show', $slug),
        };
    }

    private static function previousUrl(Event|Organisation|Venue|Provider $record): ?string
    {
        $original = $record->getOriginal('slug');

        if (! is_string($original) || $original === '' || $original === $record->slug) {
            return null;
        }

        return self::url($record, $original);
    }
}
