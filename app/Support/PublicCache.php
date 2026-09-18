<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PublicCache
{
    /**
     * @var list<string>
     */
    private const KEYS = [
        'stats',
    ];

    public static function key(string $name): string
    {
        return 'public.'.$name;
    }

    /**
     * @param  callable(): string  $build
     */
    public static function sitemap(string $name, callable $build): string
    {
        $full = implode('.', [
            $name,
            config('seo.indexable') ? 'on' : 'off',
            (string) config('seo.sitemap_chunk'),
            (string) config('seo.landing_min'),
        ]);

        $indexKey = self::key('sitemap.keys');
        $keys = Cache::get($indexKey, []);

        if (! in_array($full, $keys, true)) {
            $keys[] = $full;
            Cache::forever($indexKey, $keys);
        }

        return Cache::remember(
            self::key('sitemap.'.$full),
            (int) config('seo.sitemap_ttl'),
            $build,
        );
    }

    public static function forgetSitemaps(): void
    {
        $indexKey = self::key('sitemap.keys');

        foreach (Cache::get($indexKey, []) as $full) {
            Cache::forget(self::key('sitemap.'.$full));
        }

        Cache::forget($indexKey);
    }

    public static function bump(): void
    {
        foreach (self::KEYS as $name) {
            Cache::forget(self::key($name));
        }

        self::forgetSitemaps();
    }
}
