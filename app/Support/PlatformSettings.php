<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Platform billing config: admin settings (settings table) override config/platform.php.
 */
final class PlatformSettings
{
    /**
     * @return array<string, string|null>
     */
    public static function details(): array
    {
        $details = [];

        foreach (self::keys() as $key) {
            $details[$key] = Setting::get("platform.{$key}", (string) config("platform.{$key}"));
        }

        return $details;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'fee_bps',
            'vat_bps',
            'payout_admin_fee_cents',
            'trial_days',
            'grace_window_days',
            'single_event_price_cents',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        foreach (self::keys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            Setting::put("platform.{$key}", (string) $data[$key]);
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

        foreach (self::keys() as $key) {
            $value = Setting::get("platform.{$key}");

            if ($value === null || $value === '') {
                continue;
            }

            config(["platform.{$key}" => is_numeric($value) ? (int) $value : $value]);
        }
    }
}
