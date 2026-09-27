<?php

namespace App\Models;

use App\Enums\EntryPaymentStatus;
use App\Enums\EntryStatus;
use Database\Factories\EventEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'user_id', 'status', 'division',
    'payment_status', 'amount_cents', 'payment_reference', 'paid_at',
])]
class EventEntry extends Model
{
    /** @use HasFactory<EventEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EntryStatus::class,
            'payment_status' => EntryPaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function countsTowardCapacity(): bool
    {
        return $this->status === EntryStatus::Entered;
    }
}
