<?php

namespace App\Support;

final class YouTube
{
    public static function idFromUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        if ($host === 'youtu.be') {
            $id = trim($parts['path'] ?? '', '/');

            return self::validId($id);
        }

        if (! in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');

        if (str_starts_with($path, 'watch')) {
            parse_str($parts['query'] ?? '', $query);
            $id = $query['v'] ?? null;

            return is_string($id) ? self::validId($id) : null;
        }

        foreach (['shorts/', 'embed/', 'live/', 'v/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $id = explode('/', substr($path, strlen($prefix)))[0] ?? '';

                return self::validId($id);
            }
        }

        return null;
    }

    public static function thumbnail(?string $videoId): ?string
    {
        $id = self::validId($videoId ?? '');

        if ($id === null) {
            return null;
        }

        return 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg';
    }

    public static function watchUrl(?string $videoId): ?string
    {
        $id = self::validId($videoId ?? '');

        if ($id === null) {
            return null;
        }

        return 'https://www.youtube.com/watch?v='.$id;
    }

    private static function validId(string $id): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }
}
