<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Outgoing mail config: admin Email settings (settings table) override .env.
 */
final class MailSettings
{
    private const string SECRET_KEY = 'mailgun_secret';

    /**
     * @return array<string, string|null>
     */
    public static function details(): array
    {
        return [
            'mailer' => Setting::get('mail.mailer', (string) config('mail.default')),
            'from_address' => Setting::get('mail.from_address', (string) config('mail.from.address')),
            'from_name' => Setting::get('mail.from_name', (string) config('mail.from.name')),
            'mailgun_domain' => Setting::get('mail.mailgun_domain', (string) config('services.mailgun.domain')),
            'mailgun_secret' => self::secret(),
            'mailgun_endpoint' => Setting::get('mail.mailgun_endpoint', (string) config('services.mailgun.endpoint')),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['mailer', 'from_address', 'from_name', 'mailgun_domain', 'mailgun_secret', 'mailgun_endpoint'];
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

            Setting::put("mail.{$key}", $value !== null ? (string) $value : null);
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
            'mail.default' => $settings['mailer'] ?: config('mail.default'),
            'mail.from.address' => $settings['from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?: config('mail.from.name'),
            'services.mailgun.domain' => $settings['mailgun_domain'] ?: config('services.mailgun.domain'),
            'services.mailgun.secret' => $settings['mailgun_secret'] ?: config('services.mailgun.secret'),
            'services.mailgun.endpoint' => $settings['mailgun_endpoint'] ?: config('services.mailgun.endpoint'),
        ]);
    }

    private static function secret(): ?string
    {
        $stored = Setting::get('mail.mailgun_secret');

        if (blank($stored)) {
            return config('services.mailgun.secret');
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }
}
