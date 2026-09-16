<?php

namespace App\Console\Commands;

use App\Enums\GeocodeSource;
use App\Models\Venue;
use App\Services\Geocoding\VenueGeocoder;
use Illuminate\Console\Command;

class GeocodeVenuesCommand extends Command
{
    protected $signature = 'venues:geocode
                            {--missing-only : Only venues with no lat/lng (default behaviour)}
                            {--force : Re-geocode non-staff pins}
                            {--limit=0 : Max venues to process (0 = all)}
                            {--sleep=1100 : Milliseconds between Nominatim requests}';

    protected $description = 'Geocode venues via Nominatim. Never overwrites staff pins.';

    public function handle(VenueGeocoder $geocoder): int
    {
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $sleepMs = max(0, (int) $this->option('sleep'));

        $query = Venue::query()->orderBy('id');

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('lat')->orWhereNull('lng');
            });
        } else {
            $query->where(function ($q): void {
                $q->whereNull('geocode_source')
                    ->orWhere('geocode_source', '!=', GeocodeSource::Staff->value);
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $venues = $query->get();
        $ok = 0;
        $fail = 0;
        $skipped = 0;

        $this->info(sprintf('Geocoding %d venue(s)…', $venues->count()));

        foreach ($venues as $i => $venue) {
            if ($i > 0 && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            $did = $geocoder->fill($venue, force: $force);

            if ($did) {
                $ok++;
                $this->line("  ✓ {$venue->name} → {$venue->fresh()->lat}, {$venue->fresh()->lng}");
            } elseif ($venue->geocode_source === GeocodeSource::Staff) {
                $skipped++;
                $this->comment("  – {$venue->name} (staff pin locked)");
            } else {
                $fail++;
                $this->warn("  ✗ {$venue->name} (no hit)");
            }
        }

        $this->info("Done. {$ok} geocoded, {$fail} missed, {$skipped} staff-locked.");

        return self::SUCCESS;
    }
}
