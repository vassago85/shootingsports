<?php

namespace Database\Factories;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true).' Match';
        $starts = fake()->dateTimeBetween('+1 week', '+4 months');

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'title' => $title,
            'host_organisation_id' => Organisation::factory(),
            'venue_id' => Venue::factory(),
            'starts_at' => $starts,
            'ends_at' => null,
            'all_day' => true,
            'level' => EventLevel::Club,
            'status' => EventStatus::Confirmed,
            'confirmed_at' => now(),
            'source' => ListingSource::Staff,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Draft,
            'confirmed_at' => null,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Planned,
            'confirmed_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Cancelled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Completed,
            'starts_at' => now()->subWeek(),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subMonth(),
        ]);
    }
}
