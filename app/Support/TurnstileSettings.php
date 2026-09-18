<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cloudflare Turnstile keys: admin settings override .env.
 * Secret is stored encrypted; leave blank on save to keep the current key.
 */
final class TurnstileSettings
{
    private const string SECRET_KEY = 'secret_key';

    /**
     * @return array<string, string|null>
     */
    public static function details(): array
    {
        return [
            'site_key' => Setting::get('turnstile.site_key', (string) config('services.turnstile.site_key')),
            'secret_key' => self::secret(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['site_key', 'secret_key'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        foreach (self::keys() as $key) {
            $value = $data[$key] ?? null;

            if ($key === self::SECRET_KEY) {
                if (blank($value)) {
                    continue;
                }

                $value = Crypt::encryptString((string) $value);
            }

            Setting::put(
                "turnstile.{$key}",
                $value !== null && $value !== '' ? (string) $value : null,
            );
        }

        self::apply();
    }

    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $settings = self::details();

        config([
            'services.turnstile.site_key' => $settings['site_key'] ?: null,
            'services.turnstile.secret_key' => $settings['secret_key'] ?: null,
        ]);
    }

    private static function secret(): ?string
    {
        $stored = Setting::get('turnstile.secret_key');

        if (blank($stored)) {
            $fallback = config('services.turnstile.secret_key');

            return filled($fallback) ? (string) $fallback : null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }
}
