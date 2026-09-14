<?php

namespace App\Models\Concerns;

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
