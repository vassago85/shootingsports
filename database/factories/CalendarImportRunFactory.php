<?php

namespace Database\Factories;

use App\Models\CalendarImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarImportRun>
 */
class CalendarImportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'saprf',
            'created_count' => fake()->numberBetween(0, 5),
            'updated_count' => fake()->numberBetween(1, 20),
            'skipped_count' => 0,
            'error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'error' => 'cURL error 28: Operation timed out',
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (): array => [
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
        ]);
    }
}
