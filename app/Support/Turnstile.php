<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile server-side verification.
 *
 * In local/testing, when both site and secret keys are blank, verification
 * succeeds so Pest and local forms work without Cloudflare. Production
 * never bypasses — missing keys or a failed siteverify reject the request.
 */
final class Turnstile
{
    public static function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');

        return filled($key) ? (string) $key : null;
    }

    public static function secretKey(): ?string
    {
        $key = config('services.turnstile.secret_key');

        return filled($key) ? (string) $key : null;
    }

    public static function isConfigured(): bool
    {
        return self::siteKey() !== null && self::secretKey() !== null;
    }

    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! self::isConfigured()) {
            return app()->environment('local', 'testing');
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => self::secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            return $response->successful() && (bool) $response->json('success');
        } catch (Throwable) {
            return false;
        }
    }
}
