<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['redirectable_type', 'redirectable_id', 'slug'])]
class SlugRedirect extends Model
{
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function findTarget(string $class, string $slug): ?Model
    {
        $type = (new $class)->getMorphClass();

        return static::query()
            ->where('redirectable_type', $type)
            ->where('slug', $slug)
            ->first()
            ?->redirectable;
    }

    public static function remember(Model $model, string $oldSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $model->slug) {
            return;
        }

        static::query()->updateOrCreate(
            [
                'redirectable_type' => $model->getMorphClass(),
                'slug' => $oldSlug,
            ],
            [
                'redirectable_id' => $model->getKey(),
            ],
        );
    }

    public static function release(Model $model): void
    {
        if (! filled($model->slug)) {
            return;
        }

        static::query()
            ->where('redirectable_type', $model->getMorphClass())
            ->where('slug', $model->slug)
            ->delete();
    }
}
