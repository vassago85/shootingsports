<?php

namespace App\Imports;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Services\Geocoding\VenueGeocoder;

class CalendarImporter
{
    /**
     * @param  list<ImportedMatch>  $matches
     * @return array{created: int, updated: int, skipped: int}
     */
    public function persist(Organisation $host, array $matches): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($matches as $match) {
            $existing = Event::query()
                ->where(function ($query) use ($match): void {
                    $query->where('entry_url', $match->entryUrl)
                        ->orWhere('slug', $match->externalId);
                })
                ->first();

            if ($existing && $existing->source === ListingSource::Staff) {
                $skipped++;

                continue;
            }

            $venue = $this->venue($match);
            $payload = [
                'title' => $match->title,
                'host_organisation_id' => $host->id,
                'venue_id' => $venue?->id,
                'starts_at' => $match->startsAt,
                'ends_at' => $match->endsAt,
                'all_day' => true,
                'level' => $match->level,
                'status' => $match->status,
                'confirmed_at' => $match->status->isConfirmedDate() ? now() : null,
                'entry_url' => $match->entryUrl,
                'description' => $match->description,
                'source' => ListingSource::Import,
                'last_verified_at' => now(),
            ];

            if ($existing) {
                $existing->fill($payload)->save();
                $event = $existing;
                $updated++;
            } else {
                $event = Event::query()->create([
                    ...$payload,
                    'slug' => $match->externalId,
                ]);
                $created++;
            }

            $discipline = $match->disciplineSlug
                ? Discipline::query()->where('slug', $match->disciplineSlug)->first()
                : null;

            if ($discipline) {
                $event->syncDisciplines([$discipline->id], $discipline->id);
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    public function federation(string $slug, string $name, string $website, OrganisationType $type = OrganisationType::Federation): Organisation
    {
        return Organisation::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'short_name' => strtoupper($slug),
                'type' => $type,
                'website_url' => $website,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
                'accredited' => true,
                'visitors_welcome' => true,
            ],
        );
    }

    private function venue(ImportedMatch $match): ?Venue
    {
        if (! filled($match->venueName) || $match->province === null) {
            return null;
        }

        $venue = Venue::query()->firstOrCreate(
            [
                'name' => $match->venueName,
                'province' => $match->province,
            ],
            [
                'town' => $match->venueTown ?? $match->venueName,
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        // Soft geocode on first create / when still unpinned. Staff
        // pins are never overwritten by VenueGeocoder.
        if (! $venue->hasCoordinates()) {
            try {
                app(VenueGeocoder::class)->fill($venue);
            } catch (\Throwable) {
                // Import must not fail because Nominatim is slow/down.
            }
        }

        return $venue->fresh();
    }
}
