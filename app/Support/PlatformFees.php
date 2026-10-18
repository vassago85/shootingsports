<?php

namespace App\Support;

/**
 * Single source of truth for every platform-fee and VAT calculation
 * on the site. Keeps the Section 54 agency VAT identities in one place
 * so audits only have one method to inspect.
 */
class PlatformFees
{
    public static function feeBps(): int
    {
        return (int) config('platform.fee_bps', 200);
    }

    public static function vatBps(): int
    {
        return (int) config('platform.vat_bps', 1500);
    }

    public static function feeCents(int $grossCents): int
    {
        return (int) round($grossCents * self::feeBps() / 10000);
    }

    public static function feeVatCents(int $feeCents): int
    {
        return (int) round($feeCents * self::vatBps() / 10000);
    }

    public static function vatInclusiveCents(int $exclusiveCents): int
    {
        return (int) round($exclusiveCents * (10000 + self::vatBps()) / 10000);
    }

    /**
     * @return array{gross:int, fee:int, fee_vat:int, net_to_club_estimate:int}
     */
    public static function splitFor(int $grossCents): array
    {
        $fee = self::feeCents($grossCents);
        $vat = self::feeVatCents($fee);

        return [
            'gross' => $grossCents,
            'fee' => $fee,
            'fee_vat' => $vat,
            'net_to_club_estimate' => $grossCents - $fee - $vat,
        ];
    }
}
