<?php

namespace Database\Factories;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Shooting Range';

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'province' => fake()->randomElement(Province::cases()),
            'town' => fake()->city(),
            'lat' => fake()->optional()->latitude(-35, -22),
            'lng' => fake()->optional()->longitude(16, 33),
            'address' => fake()->optional()->address(),
            'max_distance_m' => fake()->optional()->randomElement([100, 200, 300, 600, 1000]),
            'bay_count' => fake()->optional()->numberBetween(4, 24),
            'access' => fake()->randomElement(VenueAccess::cases()),
            'day_fee_cents' => fake()->optional()->numberBetween(5000, 25000),
            'facilities' => [],
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
            'tier' => ProviderTier::Free,
        ];
    }
}
