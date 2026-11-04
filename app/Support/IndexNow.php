<?php

namespace App\Support;

use App\Enums\Province;
use App\Jobs\PingIndexNow;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

/**
 * Queues an IndexNow ping for a public listing URL.
 *
 * Off unless INDEXNOW is enabled. Production enables it when the env
 * flag is unset. Tests and local stay quiet so saves do not call out.
 *
 * Every ping path funnels through {@see self::dispatch()}, which
 * de-duplicates with `seo.indexnow_cooldown_seconds`. A URL pinged a
 * minute ago is held back until the window passes — Bing was getting
 * five pings per edit when staff clicked Save twice.
 */
class IndexNow
{
    public static function ping(string $url): void
    {
        self::dispatch($url);
    }

    public static function pingEventSurfaces(Event $event): void
    {
        if (! self::enabled()) {
            return;
        }

        $event->loadMissing(['disciplines.parent', 'venue', 'hostOrganisation']);
        $province = $event->venue?->province ?? ($event->venue_id === null ? $event->hostOrganisation?->province : null);
        $urls = [];

        foreach ($event->disciplines as $discipline) {
            $hub = $discipline->is_published ? $discipline : $discipline->parent;

            if (! $hub instanceof Discipline || ! $hub->is_published) {
                continue;
            }

            $urls[] = route('disciplines.show', $hub->slug);

            if ($province instanceof Province) {
                $urls[] = route('disciplines.province', [$hub->slug, $province->urlSlug()]);
            }
        }

        foreach (array_unique($urls) as $url) {
            self::dispatch($url);
        }
    }

    public static function dispatchFor(Event|Organisation|Venue|Provider $record): void
    {
        if (! self::enabled()) {
            return;
        }

        $urls = array_filter([
            self::url($record, $record->slug),
            self::previousUrl($record),
        ]);

        foreach (array_unique($urls) as $url) {
            self::dispatch($url);
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

    /**
     * Dispatch a single ping, debounced by URL.
     *
     * Returns silently when the engine is off, the URL is empty, or
     * the same URL was already queued within the cooldown window.
     * `Cache::add` is atomic so two concurrent saves of the same
     * listing still only queue one ping.
     */
    private static function dispatch(string $url): void
    {
        if (! self::enabled() || $url === '') {
            return;
        }

        $cooldown = (int) config('seo.indexnow_cooldown_seconds', 3600);

        if ($cooldown > 0) {
            $key = 'indexnow:'.sha1($url);

            if (! Cache::add($key, true, $cooldown)) {
                return;
            }
        }

        PingIndexNow::dispatch($url)->afterCommit();
    }

    private static function enabled(): bool
    {
        return (bool) config('services.indexnow.enabled')
            && filled(config('services.indexnow.key'));
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
