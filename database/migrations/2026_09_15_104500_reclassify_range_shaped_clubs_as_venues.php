<?php

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Two range-shaped organisations (`muletech-ridge-range`,
     * `dwarskloof-shooting-range`) were seeded as `type=club` and a
     * matching `venues` row was also created — the range wore two
     * hats. This migration collapses the duplicate: null the phantom
     * host link on each event, then soft-delete the ghost org. Only
     * runs when the matching Venue row is actually there — if it
     * isn't (e.g. a fresh clone that hasn't seeded), we leave the
     * data alone rather than orphan a match.
     *
     * Idempotent: safe to re-run — the ghost is soft-deleted, so a
     * second run finds no candidate and no-ops.
     */
    private const GHOST_SLUGS = [
        'muletech-ridge-range',
        'dwarskloof-shooting-range',
    ];

    public function up(): void
    {
        foreach (self::GHOST_SLUGS as $slug) {
            $ghost = Organisation::query()->where('slug', $slug)->first();

            if ($ghost === null) {
                continue;
            }

            $venue = Venue::query()->where('slug', $slug)->first();

            // Safety: never orphan a match. If for any reason the
            // Venue side is missing, leave the org row alone so the
            // event still has a "host" to point at.
            if ($venue === null) {
                continue;
            }

            Event::query()
                ->where('host_organisation_id', $ghost->id)
                ->update(['host_organisation_id' => null]);

            // Detach any disciplines/memberships pivots before the
            // soft-delete to keep the pivot tables tidy.
            $ghost->disciplines()->detach();
            $ghost->memberships()->delete();

            $ghost->delete();
        }
    }

    public function down(): void
    {
        // Restore is a manual staff action if ever needed — a
        // migration cannot re-invent the ghost's own attributes
        // cleanly. Restoring the soft-deleted row would leave stale
        // pivots empty anyway.
    }
};
