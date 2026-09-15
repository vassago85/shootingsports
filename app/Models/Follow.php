<?php

namespace App\Models;

use App\Support\EnforcesPlanLimits;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'followable_type', 'followable_id'])]
class Follow extends Model
{
    protected static function booted(): void
    {
        // Server-side cap. UI can be bypassed via a raw POST or the
        // console; this observer catches every create path. Duplicate
        // rows (user already follows this thing) never increment the
        // effective count because the DB's unique index rejects them.
        static::creating(function (Follow $follow): void {
            $user = $follow->user_id ? User::query()->find($follow->user_id) : null;

            if ($user === null) {
                return;
            }

            $current = self::query()->where('user_id', $user->id)->count();

            EnforcesPlanLimits::assert($user, 'follows', $current);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followable(): MorphTo
    {
        return $this->morphTo();
    }
}
