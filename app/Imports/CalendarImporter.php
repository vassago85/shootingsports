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
use App\Services\Venues\VenueResolver;
use App\Support\VenueNames;
use Illuminate\Support\Str;

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

            $scheduleFragment = filled($match->venueName) && VenueNames::isScheduleFragment($match->venueName);
            $venue = $this->venue($match);
            $provinceDisagrees = $venue !== null
                && $match->province !== null
                && $venue->province !== null
                && $venue->province !== $match->province;
            $ambiguousName = ! $scheduleFragment
                && filled($match->venueName)
                && $this->publishedNamesakes($match->venueName) > 1;

            $notes = $match->importNotes;

            if ($scheduleFragment) {
                $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '')
                    .'Multi-venue source string, left unlinked: '.$match->venueName);
            } elseif ($provinceDisagrees) {
                $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '')
                    .'Feed province '.$match->province->getLabel().' does not match the linked range '.$venue->province->getLabel().'.');
            } elseif ($ambiguousName && $venue !== null) {
                $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '')
                    .'More than one range is named "'.$match->venueName.'". The import kept the '.$venue->province?->getLabel().' row. Confirm which range is correct.');
            } elseif ($ambiguousName) {
                $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '')
                    .'More than one range is named "'.$match->venueName.'". The match was left unlinked.');
            }

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
                'import_notes' => $notes,
                'needs_review' => $match->needsReview || $scheduleFragment || $provinceDisagrees || $ambiguousName,
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
        if (! filled($match->venueName) || VenueNames::isScheduleFragment($match->venueName)) {
            return null;
        }

        // Prefer an alias-driven match before creating a new venue.
        // "Legends" / "Legends Adventure Farm" / "Legends Rayton" all
        // collapse onto the same canonical row once an alias exists.
        $resolver = app(VenueResolver::class);
        $venue = $resolver->findByName($match->venueName);

        if ($venue === null) {
            $named = Venue::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim($match->venueName))])
                ->where('status', '!=', ListingStatus::Archived)
                ->get();

            if ($named->count() === 1) {
                // One range already uses this name. Attach the match to
                // it even when the feed's province is the federation's
                // home province rather than the range's.
                $venue = $named->first();
            } elseif ($named->count() > 1) {
                // Do not mint a third row. Prefer the namesake whose
                // province matches the feed; otherwise leave the match
                // unlinked for a person to resolve.
                $venue = $match->province === null
                    ? null
                    : $named->first(fn (Venue $row): bool => $row->province === $match->province);

                if ($venue === null) {
                    return null;
                }
            } elseif ($match->province === null) {
                return null;
            } else {
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
            }
        }

        if ($venue->name !== $match->venueName) {
            // Alias resolved but the incoming string is a new spelling
            // we have not seen before — record it so the *next* import
            // is a direct hit rather than a fuzzy fingerprint later.
            $resolver->rememberAliasName($venue, $match->venueName);
        }

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

    private function publishedNamesakes(string $name): int
    {
        return Venue::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->where('status', '!=', ListingStatus::Archived)
            ->count();
    }
}
