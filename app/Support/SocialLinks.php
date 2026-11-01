<?php

namespace App\Support;

/**
 * Shared normaliser for the four social URL columns stored on
 * organisations and events (website, Facebook, Instagram, WhatsApp).
 *
 * Returns a list of link rows the <x-social-links> component and the
 * JSON-LD `sameAs` builder both consume, so the ordering, labels and
 * null-filtering stay in one place.
 */
final class SocialLinks
{
    /**
     * @param  array{website_url?: ?string, facebook_url?: ?string, instagram_url?: ?string, whatsapp_url?: ?string}  $sources
     * @return list<array{key: 'website'|'facebook'|'instagram'|'whatsapp', label: string, url: string}>
     */
    public static function fromArray(array $sources): array
    {
        $rows = [
            ['key' => 'website', 'label' => 'Website', 'url' => self::clean($sources['website_url'] ?? null)],
            ['key' => 'facebook', 'label' => 'Facebook', 'url' => self::clean($sources['facebook_url'] ?? null)],
            ['key' => 'instagram', 'label' => 'Instagram', 'url' => self::clean($sources['instagram_url'] ?? null)],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'url' => self::clean($sources['whatsapp_url'] ?? null)],
        ];

        return array_values(array_filter($rows, fn (array $row): bool => $row['url'] !== null));
    }

    /**
     * Merge two source bags, keeping the first non-empty value per
     * platform. Used by events to inherit missing links from the host
     * organisation (an event with no Instagram still shows the series
     * Instagram when the host has one).
     *
     * @param  array{website_url?: ?string, facebook_url?: ?string, instagram_url?: ?string, whatsapp_url?: ?string}  $primary
     * @param  array{website_url?: ?string, facebook_url?: ?string, instagram_url?: ?string, whatsapp_url?: ?string}  $fallback
     * @return list<array{key: 'website'|'facebook'|'instagram'|'whatsapp', label: string, url: string}>
     */
    public static function merged(array $primary, array $fallback): array
    {
        $merged = [];

        foreach (['website_url', 'facebook_url', 'instagram_url', 'whatsapp_url'] as $column) {
            $merged[$column] = self::clean($primary[$column] ?? null) ?? self::clean($fallback[$column] ?? null);
        }

        return self::fromArray($merged);
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
