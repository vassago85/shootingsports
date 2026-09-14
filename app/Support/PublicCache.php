<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PublicCache
{
    public static function version(): int
    {
        return (int) Cache::get('public.cache_version', 1);
    }

    public static function bump(): void
    {
        Cache::forever('public.cache_version', self::version() + 1);
    }

    public static function key(string $name): string
    {
        return 'public.v'.self::version().'.'.$name;
    }
}
