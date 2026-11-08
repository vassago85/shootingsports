<?php

namespace App\Models;

use App\Support\EnforcesPlanLimits;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event_id'])]
class SavedEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SavedEvent $saved): void {
            $user = $saved->user_id ? User::query()->find($saved->user_id) : null;

            if ($user === null) {
                return;
            }

            $current = self::query()->where('user_id', $user->id)->count();

            EnforcesPlanLimits::assert($user, 'saved_events', $current);
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
}
