<?php

namespace Database\Factories;

use App\Models\ClubGuestVisit;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubGuestVisit>
 */
class ClubGuestVisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'visit_date' => fake()->dateTimeBetween('-30 days', 'today'),
            'host_member_id' => null,
            'notes' => null,
            'created_by' => null,
            'converted_club_member_id' => null,
        ];
    }
}
