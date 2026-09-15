<?php

namespace App\Support;

use App\Enums\ProviderTier;

/**
 * Bridge from ProviderTier enum cases to public rate-card prices.
 *
 * The tier enum stays product-neutral (free / verified / featured);
 * marketing display prices live in config/advertising.php under the
 * matching product key. This helper keeps the mapping in one place so
 * a rate-card price change never leaks into a Filament form or vice
 * versa.
 */
class ProviderTierPricing
{
    /**
     * Product key in config/advertising.php for a given tier.
     * Verified is a moderation state, not a purchased product — it
     * has no rate card row.
     */
    private const PRODUCT_FOR = [
        'featured' => 'featured',
    ];

    /**
     * Human-friendly helper text for the tier Select in Filament.
     * Returns null when there is no purchased-product signal to show.
     */
    public static function helperFor(ProviderTier|string|null $tier): ?string
    {
        $key = $tier instanceof ProviderTier ? $tier->value : (string) $tier;

        return match ($key) {
            'free' => 'Default free listing — appears in the industry directory alphabetically.',
            'verified' => 'Staff-confirmed listing — no charge. Sits above free listings.',
            'featured' => self::featuredHelper(),
            default => null,
        };
    }

    private static function featuredHelper(): string
    {
        $products = config('advertising.products', []);
        $productKey = self::PRODUCT_FOR['featured'] ?? null;

        $price = $productKey && isset($products[$productKey])
            ? $products[$productKey]['price_display']
            : null;

        $suffix = $price ? " · {$price}" : '';

        return 'Paid enhanced listing — top of the industry directory, photos, contact form'.$suffix.'.';
    }
}
