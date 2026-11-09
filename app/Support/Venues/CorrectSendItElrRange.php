<?php

namespace App\Support\Venues;

use App\Enums\GeocodeSource;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;

/**
 * The Send It 2-mile range was stored as Matroosbergstasie Skietklub
 * in the town Matroosberg. That name is the old Boomsticks club, which
 * no longer operates, and Matroosberg is the snow mountain above Ceres.
 * The range is close to Matroosbergstasie. Exact coordinates are still to come.
 */
class CorrectSendItElrRange
{
    public const SLUG = 'send-it-elr-2-mile-range';

    public const PREVIOUS_SLUG = 'matroosbergstasie-skietklub';

    public function __invoke(): void
    {
        Organisation::query()->where('slug', 'send-it-elr')->update([
            'description' => $this->seriesDescription(),
            'town' => null,
        ]);

        Event::query()->whereIn('slug', [
            'send-it-1-mile-challenge-q1-2026',
            'send-it-1-mile-challenge-q2-2026',
            'send-it-1-mile-challenge-q3-2026',
            'send-it-1-mile-challenge-final-2026',
        ])->update([
            'description' => 'Hosted by APW and 3% Tactical at Darling Steel Valley.',
        ]);

        Venue::query()->where('slug', 'darling-steel-valley')->update([
            'notes' => '1-mile challenges hosted with APW and 3% Tactical.',
        ]);

        $venue = Venue::query()->where('slug', self::PREVIOUS_SLUG)->first()
            ?? Venue::query()->where('slug', self::SLUG)->first();

        if (! $venue instanceof Venue) {
            return;
        }

        $venue->fill($this->place());
        $venue->slug = self::SLUG;
        $venue->save();

        foreach ($this->eventDescriptions() as $slug => $description) {
            Event::query()->where('slug', $slug)->update(['description' => $description]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function place(): array
    {
        return [
            'name' => 'Send It ELR 2-Mile Range',
            'province' => Province::WesternCape,
            'town' => 'Matroosbergstasie',
            'address' => 'Close to Matroosbergstasie, off the R318 towards Montagu',
            'lat' => '-33.4291500',
            'lng' => '19.8374800',
            'geocode_source' => GeocodeSource::Staff,
            'geocoded_at' => now(),
            'max_distance_m' => 3219,
            'description' => null,
            'notes' => null,
        ];
    }

    /**
     * Fields for a first insert. An existing row keeps its own
     * publishing state and only receives {@see place()}.
     *
     * @return array<string, mixed>
     */
    public function newVenue(): array
    {
        return [
            ...$this->place(),
            'access' => VenueAccess::GuestByArrangement,
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];
    }

    public function seriesDescription(): string
    {
        return '1-mile and 2-mile challenges, .22 ELR, and Send It Ultra. Hosted with APW, 3% Tactical and PEKS.';
    }

    public function eventDescription(string $slug): string
    {
        return $this->eventDescriptions()[$slug] ?? '2-mile challenge close to Matroosbergstasie, off the R318 towards Montagu.';
    }

    /**
     * @return array<string, string>
     */
    public function eventDescriptions(): array
    {
        $standard = '2-mile challenge close to Matroosbergstasie, off the R318 towards Montagu.';

        return [
            'send-it-2-mile-challenge-q1-2026' => $standard,
            'send-it-2-mile-challenge-q2-2026' => $standard,
            'send-it-2-mile-challenge-q3-2026' => $standard,
            'send-it-2-mile-challenge-final-2026' => '2-mile challenge final close to Matroosbergstasie, off the R318 towards Montagu. Prizegiving at the Karoo Huisies.',
        ];
    }
}
