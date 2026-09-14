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

    public static function bump(): void
    {
        foreach (self::KEYS as $name) {
            Cache::forget(self::key($name));
        }
    }
}
