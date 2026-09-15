<?php

namespace App\Models;

use App\Support\EnforcesPlanLimits;
use Database\Factories\AttendedEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single row in a shooter's personal attendance log. See the
 * migration for the "why every field is denormalised" rationale.
 *
 * Cap enforcement lives in the booted() observer, not in the
 * controller — so a raw POST, an artisan command, or a future
 * import path all get gated by the same rule.
 */
#[Fillable([
    'user_id', 'event_id', 'discipline_id',
    'event_name_snapshot', 'event_date',
    'discipline_name_snapshot', 'venue_snapshot', 'host_snapshot',
    'division', 'classification', 'placing', 'field_size', 'score', 'notes',
])]
class AttendedEvent extends Model
{
    /** @use HasFactory<AttendedEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'placing' => 'integer',
            'field_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AttendedEvent $entry): void {
            $user = $entry->user_id ? User::query()->find($entry->user_id) : null;

            if ($user === null) {
                return;
            }

            // Duplicate re-logs of the same DB-linked event are caught
            // by the unique index (user_id, event_id) — the observer
            // does not need to count them because those rows never
            // create successfully anyway. Only slot-counted entries
            // matter here.
            $current = self::query()->where('user_id', $user->id)->count();

            EnforcesPlanLimits::assert($user, 'attended_events_slots', $current);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }
}
