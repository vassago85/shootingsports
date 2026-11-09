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

        Organisation::query()->where('slug', 'send-it-elr')->update([
            'description' => $this->seriesDescription(),
        ]);
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
            'description' => $this->description(),
            'notes' => 'Map pin is the Matroosberg railway halt. The range is close to Matroosbergstasie. Exact range GPS is still to come.',
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

    public function description(): string
    {
        return <<<'TEXT'
The Send It ELR 2-mile range is close to Matroosbergstasie, off the R318 on the way to Montagu. Exact coordinates are still to come. Charl Gous signposts the turnoff from the R318 for events. The range is run with Estian du Preez, who farms at Matroosbergstasie. Prizegiving for the 2-mile championship is at the Karoo Huisies on the farm.

Matroosberg is the snow mountain above Ceres, not this range. The old Boomsticks range, Matroosbergstasie Skietklub, still has fences facing the R318 but no longer operates. The 1-mile challenge that used those lower platforms moved to Darling Steel Valley at the start of 2026.
TEXT;
    }

    public function seriesDescription(): string
    {
        return 'ELR series: 1-mile and 2-mile challenges, .22 ELR, and Send It Ultra. Hosted with APWY, 3% Tactical, PEKS, Estian du Preez, Lambert Brand and Taai Boskraal.';
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
