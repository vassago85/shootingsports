<?php

namespace App\Services\Venues;

use App\Enums\ListingStatus;
use App\Models\Venue;
use App\Models\VenueAlias;
use Illuminate\Support\Str;

/**
 * Look a range up by canonical slug or by any of its aliases.
 *
 * Used by:
 *   - `VenueController::show` to serve `/ranges/{slug}` and to 301
 *     old slugs onto the canonical URL after a merge.
 *   - `CalendarImporter` to prevent third-party calendar strings
 *     from spawning duplicate venue rows.
 */
class VenueResolver
{
    /**
     * @return array{venue: Venue, canonical: bool}|null
     *
     * `canonical` is false when the caller reached the venue via an
     * alias — the caller should 301 to `ranges.show` with
     * `$venue->slug` to preserve link equity.
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $venue = Venue::query()->where('slug', $slug)->first();

        if ($venue !== null && $venue->status === ListingStatus::Published) {
            return ['venue' => $venue, 'canonical' => true];
        }

        // Alias slug lookup handles two cases together: a venue that
        // was merged and archived (loser slug lives on `venue_aliases`
        // pointing at the winning venue), and an alias that staff
        // added manually before any merge happened.
        $alias = VenueAlias::query()->where('slug', $slug)->first();

        if ($alias === null) {
            return null;
        }

        $canonical = $alias->venue()->first();

        if ($canonical === null || $canonical->status !== ListingStatus::Published) {
            return null;
        }

        return ['venue' => $canonical, 'canonical' => false];
    }

    /**
     * Match an incoming venue name (from a third-party calendar or a
     * seeder) against a canonical venue via its aliases. Returns null
     * when there is no confident match — the caller then falls back
     * to `firstOrCreate` on the `venues` table.
     */
    public function findByName(string $name): ?Venue
    {
        $normalised = $this->normalise($name);

        if ($normalised === '') {
            return null;
        }

        // 1. Exact alias name match. Case-insensitive because imports
        //    round-trip through disparate spreadsheets.
        $alias = VenueAlias::query()
            ->whereRaw('LOWER(name) = ?', [$normalised])
            ->first();

        if ($alias !== null) {
            $venue = $alias->venue()->first();

            if ($venue !== null && $venue->status !== ListingStatus::Archived) {
                return $venue;
            }
        }

        return null;
    }

    /**
     * Record an incoming name as an alias against the canonical venue
     * so future imports of the same string skip straight to
     * `findByName`. Idempotent: existing rows are left alone.
     */
    public function rememberAliasName(Venue $canonical, string $name, string $source = 'import'): void
    {
        $name = trim($name);

        if ($name === '' || $name === $canonical->name) {
            return;
        }

        VenueAlias::query()->firstOrCreate(
            ['venue_id' => $canonical->id, 'name' => $name],
            ['source' => $source],
        );
    }

    private function normalise(string $value): string
    {
        return Str::lower(trim($value));
    }
}
