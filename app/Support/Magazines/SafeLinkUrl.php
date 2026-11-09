<?php

namespace App\Support\Magazines;

/**
 * Accepts only http(s) and mailto targets. Anything else is dropped
 * so a stored hotspot cannot become an open redirect or a script URL.
 */
final class SafeLinkUrl
{
    public static function make(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^mailto:/i', $value) === 1) {
            $email = substr($value, 7);

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$email : null;
        }

        if (preg_match('/^www\./i', $value) === 1 || self::looksLikeDomain($value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (! is_string($parts['host'] ?? null) || $parts['host'] === '') {
            return null;
        }

        return $value;
    }

    private static function looksLikeDomain(string $value): bool
    {
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,62}[a-z0-9])?\.)+(?:co\.za|com|org|net|info|africa|co\.uk|io)(?:[\/?#].*)?$/i', $value) === 1;
    }
}
