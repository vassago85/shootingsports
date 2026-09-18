<?php

namespace Database\Factories;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Rifle Club';

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'short_name' => fake()->optional()->lexify('????'),
            'type' => OrganisationType::Club,
            'province' => fake()->randomElement(Province::cases()),
            'town' => fake()->city(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'website_url' => fake()->optional()->url(),
            'description' => fake()->optional()->paragraph(),
            'accredited' => false,
            'visitors_welcome' => fake()->boolean(40),
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification_state' => VerificationState::Verified,
            'last_verified_at' => now(),
        ]);
    }
}
