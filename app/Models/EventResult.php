<?php

namespace App\Models;

use Database\Factories\EventResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'user_id', 'display_name', 'discipline_id',
    'division', 'placing', 'field_size', 'score',
])]
class EventResult extends Model
{
    /** @use HasFactory<EventResultFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'placing' => 'integer',
            'field_size' => 'integer',
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

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function isWin(): bool
    {
        return $this->placing === 1;
    }

    public function isPodium(): bool
    {
        return $this->placing !== null && $this->placing >= 1 && $this->placing <= 3;
    }
}
