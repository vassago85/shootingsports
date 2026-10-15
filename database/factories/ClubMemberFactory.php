<?php

namespace Database\Factories;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => null,
            'member_number' => fake()->unique()->numerify('M####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'category' => ClubMemberCategory::Senior,
            'status' => ClubMemberStatus::Active,
            'joined_at' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'expires_at' => now()->addYear()->toDateString(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'popia_consented_at' => now(),
            'notes' => null,
        ];
    }

    public function applied(): static
    {
        return $this->state(fn (): array => [
            'status' => ClubMemberStatus::Applied,
            'joined_at' => null,
            'expires_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ClubMemberStatus::Active,
            'joined_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    }

    public function junior(): static
    {
        return $this->state(fn (): array => [
            'category' => ClubMemberCategory::Junior,
            'date_of_birth' => now()->subYears(14)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => ClubMemberStatus::Active,
            'expires_at' => now()->subMonth()->toDateString(),
        ]);
    }
}
