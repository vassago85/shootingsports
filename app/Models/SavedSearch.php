<?php

namespace App\Models;

use App\Support\EnforcesPlanLimits;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'params'])]
class SavedSearch extends Model
{
    protected static function booted(): void
    {
        static::creating(function (SavedSearch $search): void {
            $user = $search->user_id ? User::query()->find($search->user_id) : null;

            if ($user === null) {
                return;
            }

            $current = self::query()->where('user_id', $user->id)->count();

            EnforcesPlanLimits::assert($user, 'saved_searches', $current);
        });
    }

    protected function casts(): array
    {
        return [
            'params' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
