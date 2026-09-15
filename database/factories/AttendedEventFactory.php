<?php

namespace Database\Factories;

use App\Models\AttendedEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendedEvent>
 */
class AttendedEventFactory extends Factory
{
    protected $model = AttendedEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default entry is a manual one (no event_id / discipline_id)
        // — the safest state for tests that need to seed history
        // without dragging in real Event / Discipline factories.
        return [
            'user_id' => User::factory(),
            'event_id' => null,
            'discipline_id' => null,
            'event_name_snapshot' => fake()->words(3, true).' Match',
            'event_date' => fake()->dateTimeBetween('-2 years', '-1 week')->format('Y-m-d'),
            'discipline_name_snapshot' => fake()->randomElement(['IPSC Handgun', 'IPSC Rifle', 'Steel Challenge', 'Bullseye', 'Precision Rifle']),
            'venue_snapshot' => fake()->city().' Shooting Range',
            'host_snapshot' => fake()->company(),
            'division' => fake()->randomElement(['Standard', 'Production', 'Open', 'Optics', null]),
            'classification' => fake()->randomElement(['A', 'B', 'C', 'D', 'M', 'GM', null]),
            'placing' => fake()->optional(0.6)->numberBetween(1, 30),
            'field_size' => fake()->optional(0.6)->numberBetween(5, 50),
            'score' => fake()->optional(0.5)->numerify('##.##%'),
            'notes' => null,
        ];
    }
}
