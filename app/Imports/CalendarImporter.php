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

            $outcome = $this->resolveVenue($match);
            $notes = $match->importNotes;

            if ($outcome['note'] !== null) {
                $notes = trim(($notes !== null && $notes !== '' ? $notes."\n" : '').$outcome['note']);
            }

            $payload = [
                'title' => $match->title,
                'host_organisation_id' => $host->id,
                'venue_id' => $outcome['venue']?->id,
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
                'needs_review' => $match->needsReview || $outcome['needsReview'],
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

    /**
     * @return array{venue: ?Venue, note: ?string, needsReview: bool}
     */
    private function resolveVenue(ImportedMatch $match): array
    {
        if (! filled($match->venueName)) {
            return ['venue' => null, 'note' => null, 'needsReview' => false];
        }

        if (VenueNames::isScheduleFragment($match->venueName)) {
            return [
                'venue' => null,
                'note' => 'Multi-venue source string, left unlinked: '.$match->venueName,
                'needsReview' => true,
            ];
        }

        // Prefer an alias-driven match before creating a new venue.
        // "Legends" / "Legends Adventure Farm" / "Legends Rayton" all
        // collapse onto the same canonical row once an alias exists.
        $resolver = app(VenueResolver::class);
        $viaAlias = $resolver->findByName($match->venueName);

        if ($viaAlias !== null) {
            return ['venue' => $this->ensureGeocoded($viaAlias), 'note' => null, 'needsReview' => false];
        }

        $named = Venue::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($match->venueName))])
            ->where('status', '!=', ListingStatus::Archived)
            ->get();

        if ($named->count() >= 2) {
            $sameProvince = $match->province === null
                ? null
                : $named->first(fn (Venue $row): bool => $row->province === $match->province);

            if ($sameProvince === null) {
                $provinces = $named->map(fn (Venue $row): ?string => $row->province?->getLabel())
                    ->filter()
                    ->implode(', ');

                return [
                    'venue' => null,
                    'note' => 'More than one range is named "'.$match->venueName.'" ('.$provinces.'). Match left unlinked for review.',
                    'needsReview' => true,
                ];
            }

            $this->rememberAlias($sameProvince, $match->venueName);

            return [
                'venue' => $this->ensureGeocoded($sameProvince),
                'note' => 'More than one range is named "'.$match->venueName.'". Linked the '.$sameProvince->province->getLabel().' row. Confirm this is correct.',
                'needsReview' => true,
            ];
        }

        if ($named->count() === 1) {
            $existing = $named->first();
            $provinceDisagrees = $match->province !== null
                && $existing->province !== null
                && $existing->province !== $match->province;

            if ($provinceDisagrees) {
                // A verified range already carries this name in another
                // province. Do not auto-link, do not write an alias that
                // would lock the mistake in. Leave venue_id null so staff
                // can confirm whether this is the same place.
                return [
                    'venue' => null,
                    'note' => 'A range named "'.$match->venueName.'" exists in '.$existing->province->getLabel().' but the feed says '.$match->province->getLabel().'. Match left unlinked for review.',
                    'needsReview' => true,
                ];
            }

            $this->rememberAlias($existing, $match->venueName);

            return ['venue' => $this->ensureGeocoded($existing), 'note' => null, 'needsReview' => false];
        }

        if ($match->province === null) {
            return [
                'venue' => null,
                'note' => 'Feed did not say which province the range "'.$match->venueName.'" is in. Match left unlinked.',
                'needsReview' => true,
            ];
        }

        $created = Venue::query()->firstOrCreate(
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

        return ['venue' => $this->ensureGeocoded($created), 'note' => null, 'needsReview' => false];
    }

    private function rememberAlias(Venue $venue, string $incomingName): void
    {
        if ($venue->name === $incomingName) {
            return;
        }

        app(VenueResolver::class)->rememberAliasName($venue, $incomingName);
    }

    private function ensureGeocoded(Venue $venue): Venue
    {
        // Staff pins are protected by VenueGeocoder; this only fills
        // coordinates on a venue that still has none.
        if (! $venue->hasCoordinates()) {
            try {
                app(VenueGeocoder::class)->fill($venue);
            } catch (\Throwable) {
                // Import must not fail because Nominatim is slow or down.
            }
        }

        return $venue->fresh();
    }
}
