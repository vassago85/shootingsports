<?php

namespace Database\Factories;

use App\Enums\EntryPaymentStatus;
use App\Enums\EntryStatus;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventEntry>
 */
class EventEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => EntryStatus::Entered,
            'payment_status' => EntryPaymentStatus::Pending,
            'amount_cents' => 15000,
        ];
    }
}
