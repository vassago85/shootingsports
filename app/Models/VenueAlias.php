<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A `VenueAlias` maps an alternate name (or a superseded slug) onto a
 * canonical `Venue`. Two problems this table solves:
 *
 * 1. Import matching. Third-party calendars publish the same range
 *    under drifting strings — "Legends", "Legends Adventure Farm",
 *    "Legends Adv Farm", "Legends Rayton". The importer looks the
 *    incoming name up here before falling back to `firstOrCreate` on
 *    `venues`, so the range is not silently duplicated.
 *
 * 2. Post-merge redirects. When staff merge duplicate venues, we keep
 *    the loser's slug on this table so `/ranges/{old-slug}` still
 *    resolves and 301s to the canonical venue.
 */
#[Fillable(['venue_id', 'name', 'slug', 'source'])]
class VenueAlias extends Model
{
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
