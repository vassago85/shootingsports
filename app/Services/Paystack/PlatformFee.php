<?php

namespace App\Services\Paystack;

use App\Enums\PaystackFeeBearer;
use App\Enums\VatTreatment;
use InvalidArgumentException;

/**
 * Turns the configured flat fee and VAT treatment into the
 * transaction_charge sent to Paystack. All amounts are cents.
 */
final class PlatformFee
{
    public function quote(int $grossCents): PlatformFeeQuote
    {
        if ($grossCents < 1) {
            throw new InvalidArgumentException('Gross amount must be at least 1 cent.');
        }

        $treatment = VatTreatment::tryFrom((string) config('paystack.vat_treatment'));

        if (! $treatment instanceof VatTreatment) {
            throw new InvalidArgumentException('Paystack VAT treatment must be exclusive, inclusive, or exempt.');
        }

        $bearer = PaystackFeeBearer::tryFrom((string) config('paystack.fee_bearer'));

        if (! $bearer instanceof PaystackFeeBearer) {
            throw new InvalidArgumentException('Paystack fee bearer must be account or subaccount.');
        }

        $configuredFeeCents = (int) config('paystack.platform_fee_cents');

        if ($configuredFeeCents < 0) {
            throw new InvalidArgumentException('Platform fee cannot be negative.');
        }

        $basisPoints = (int) config('paystack.vat_rate_basis_points');

        if ($basisPoints < 0) {
            throw new InvalidArgumentException('VAT rate cannot be negative.');
        }

        [$platformFeeCents, $vatOnFeeCents, $transactionChargeCents] = match ($treatment) {
            VatTreatment::Exclusive => $this->exclusive($configuredFeeCents, $basisPoints),
            VatTreatment::Inclusive => $this->inclusive($configuredFeeCents, $basisPoints),
            VatTreatment::Exempt => [$configuredFeeCents, 0, $configuredFeeCents],
        };

        if ($transactionChargeCents >= $grossCents) {
            throw new InvalidArgumentException('Platform fee including VAT must be less than the entry amount.');
        }

        return new PlatformFeeQuote(
            grossCents: $grossCents,
            platformFeeCents: $platformFeeCents,
            vatOnFeeCents: $vatOnFeeCents,
            transactionChargeCents: $transactionChargeCents,
            bearer: $bearer,
            vatTreatment: $treatment,
            vatRateBasisPoints: $basisPoints,
            estimatedClubShareCents: $grossCents - $transactionChargeCents,
        );
    }

    /**
     * @return array{int, int, int}
     */
    private function exclusive(int $feeCents, int $basisPoints): array
    {
        $vatCents = $this->portion($feeCents, $basisPoints, 10000);

        return [$feeCents, $vatCents, $feeCents + $vatCents];
    }

    /**
     * The configured fee already includes VAT.
     *
     * @return array{int, int, int}
     */
    private function inclusive(int $chargeCents, int $basisPoints): array
    {
        $vatCents = $this->portion($chargeCents, $basisPoints, 10000 + $basisPoints);

        return [$chargeCents - $vatCents, $vatCents, $chargeCents];
    }

    private function portion(int $amountCents, int $basisPoints, int $divisor): int
    {
        return (int) round($amountCents * $basisPoints / $divisor);
    }
}
