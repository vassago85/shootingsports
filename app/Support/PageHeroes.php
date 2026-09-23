<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PageHeroes
{
    /**
     * Public pages whose hero picture staff can replace from the desk.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'home' => 'Homepage',
            'matches' => 'Matches',
            'calendar' => 'Calendar',
            'clubs' => 'Clubs',
            'ranges' => 'Ranges',
            'sports' => 'Sports',
            'industry' => 'Industry',
        ];
    }

    public static function path(string $page): ?string
    {
        if (! self::isPage($page)) {
            return null;
        }

        $stored = Setting::get(self::key($page));

        return filled($stored) ? $stored : null;
    }

    public static function url(string $page): ?string
    {
        if (! self::isPage($page)) {
            return null;
        }

        return Cache::remember(self::cacheKey($page), 3600, function () use ($page): ?string {
            $path = self::path($page);

            if ($path !== null && self::isStoredPhoto($path) && Storage::disk('media')->exists($path)) {
                return Storage::disk('media')->url($path);
            }

            return self::fallbackUrl($page);
        });
    }

    public static function save(string $page, ?string $path): void
    {
        if (! self::isPage($page)) {
            return;
        }

        $next = filled($path) ? $path : null;

        if ($next !== null && ! self::isStoredPhoto($next)) {
            return;
        }

        $previous = self::path($page);

        if ($previous !== null && $previous !== $next && self::isStoredPhoto($previous)) {
            Storage::disk('media')->delete($previous);
        }

        Setting::put(self::key($page), $next);
        Cache::forget(self::cacheKey($page));
    }

    public static function fallbackUrl(string $page): ?string
    {
        $file = match ($page) {
            'matches', 'calendar', 'sports' => 'hero-match.jpg',
            'home', 'clubs', 'ranges', 'industry' => 'hero-range.jpg',
            default => null,
        };

        if ($file === null || ! is_file(public_path('mockup-assets/'.$file))) {
            return null;
        }

        return asset('mockup-assets/'.$file);
    }

    public static function isStoredPhoto(string $path): bool
    {
        return str_starts_with($path, 'page-heroes/') && ! str_contains($path, '..');
    }

    private static function isPage(string $page): bool
    {
        return array_key_exists($page, self::pages());
    }

    private static function key(string $page): string
    {
        return 'page.hero.'.$page;
    }

    private static function cacheKey(string $page): string
    {
        return 'page.hero.url.'.$page;
    }
}
