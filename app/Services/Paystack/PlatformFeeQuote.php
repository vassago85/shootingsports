<?php

namespace App\Services\Paystack;

use App\Enums\PaystackFeeBearer;
use App\Enums\VatTreatment;

/**
 * Snapshot of one match-entry split. Amounts are cents.
 * estimatedClubShareCents ignores Paystack's own fee, which is only
 * known after the transaction is verified.
 */
final readonly class PlatformFeeQuote
{
    public function __construct(
        public int $grossCents,
        public int $platformFeeCents,
        public int $vatOnFeeCents,
        public int $transactionChargeCents,
        public PaystackFeeBearer $bearer,
        public VatTreatment $vatTreatment,
        public int $vatRateBasisPoints,
        public int $estimatedClubShareCents,
    ) {}
}
