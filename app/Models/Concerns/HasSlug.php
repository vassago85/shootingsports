<?php

namespace App\Models\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
            if (filled($model->slug)) {
                return;
            }

            $source = $model->slugSource();
            $model->slug = $model->uniqueSlug(Str::slug($source));
        });

        static::created(function (self $model): void {
            SlugRedirect::release($model);
        });

        static::saving(function (self $model): void {
            SlugRedirect::release($model);
        });

        static::updating(function (self $model): void {
            if (! $model->isDirty('slug')) {
                return;
            }

            $previous = $model->getOriginal('slug');

            if (! is_string($previous) || $previous === '') {
                return;
            }

            SlugRedirect::remember($model, $previous);
        });
    }

    protected function slugSource(): string
    {
        return (string) ($this->name ?? $this->title ?? '');
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'listing';
        $candidate = $slug;
        $i = 2;

        while (static::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
