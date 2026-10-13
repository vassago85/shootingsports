<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TeamEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamEntry>
 */
class TeamEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'team_name' => fake()->words(2, true),
            'captain_name' => fake()->name(),
            'captain_email' => fake()->safeEmail(),
            'captain_phone' => fake()->numerify('08# ### ####'),
            'members' => collect(range(1, 4))->map(fn (): array => [
                'name' => fake()->name(),
                'id_number' => fake()->numerify('#############'),
                'phone' => fake()->numerify('08# ### ####'),
            ])->all(),
        ];
    }
}
