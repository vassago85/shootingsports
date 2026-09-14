<?php

namespace App\Support;

class EmbedUrl
{
    /**
     * @var list<string>
     */
    public const QUERY_KEYS = [
        'club',
        'organisation',
        'venue',
        'shooter',
        'discipline',
        'province',
        'accent',
        'color',
        'bg',
        'ink',
        'font',
        'theme',
        'height',
    ];

    /**
     * @param  array<string, string>  $query
     */
    public function __construct(
        public string $calendarUrl,
        public array $query,
        public int $height,
    ) {}

    public static function fromString(string $url): ?self
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || ! in_array($host, self::allowedHosts(), true)) {
            return null;
        }

        $path = rtrim($parts['path'] ?? '', '/') ?: '/';

        if ($path !== '/embed/calendar') {
            return null;
        }

        parse_str($parts['query'] ?? '', $raw);

        $query = [];

        foreach (self::QUERY_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            $value = $raw[$key];

            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $clean = self::sanitizeQueryValue($key, $value);

            if ($clean === null) {
                continue;
            }

            $query[$key] = $clean;
        }

        $height = isset($query['height']) ? (int) $query['height'] : 520;
        $height = max(320, min(1600, $height));

        $calendarUrl = url('/embed/calendar');

        if ($query !== []) {
            $calendarUrl .= '?'.http_build_query($query);
        }

        return new self($calendarUrl, $query, $height);
    }

    /**
     * @return list<string>
     */
    private static function allowedHosts(): array
    {
        $hosts = [];

        foreach ([config('app.url'), request()->getSchemeAndHttpHost()] as $base) {
            $host = strtolower((string) parse_url((string) $base, PHP_URL_HOST));

            if ($host === '') {
                continue;
            }

            $hosts[] = $host;
            $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
        }

        return array_values(array_unique($hosts));
    }

    private static function sanitizeQueryValue(string $key, string $value): ?string
    {
        if (in_array($key, ['accent', 'color', 'bg', 'ink'], true)) {
            return EmbedTheme::hex($value);
        }

        if ($key === 'font') {
            $font = strtolower($value);

            return array_key_exists($font, EmbedTheme::FONTS) ? $font : null;
        }

        if ($key === 'theme') {
            $theme = strtolower($value);

            return in_array($theme, ['light', 'dark'], true) ? $theme : null;
        }

        if ($key === 'height') {
            return (string) max(320, min(1600, (int) $value));
        }

        return $value;
    }
}
