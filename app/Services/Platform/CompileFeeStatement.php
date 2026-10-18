<?php

namespace App\Services\Platform;

use App\Enums\MembershipInvoiceStatus;
use App\Models\Organisation;
use App\Models\PlatformFeeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Compiles (or refreshes) the platform fee statement for one
 * organisation and period from its paid membership invoices.
 *
 * Idempotent: the statement is keyed on (organisation, period_start,
 * period_end) and each line on (statement, invoice), so re-running
 * only adds invoices paid since the last run and recomputes totals
 * from the lines. A period with no fee-bearing invoices still yields
 * a statement with zero totals and no lines, so the ledger has a row
 * for every compiled month.
 */
class CompileFeeStatement
{
    /**
     * @param  CarbonImmutable  $periodStart  Inclusive; widened to start of day.
     * @param  CarbonImmutable  $periodEnd  Inclusive; widened to end of day.
     */
    public function for(Organisation $organisation, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): PlatformFeeStatement
    {
        return DB::transaction(function () use ($organisation, $periodStart, $periodEnd): PlatformFeeStatement {
            $statement = PlatformFeeStatement::firstOrCreate(
                [
                    'organisation_id' => $organisation->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'total_fee_cents' => 0,
                    'total_vat_cents' => 0,
                    'total_gross_cents' => 0,
                ],
            );

            $invoices = $organisation->membershipInvoices()
                ->where('status', MembershipInvoiceStatus::Paid->value)
                ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
                ->where('platform_fee_cents', '>', 0)
                ->get();

            foreach ($invoices as $invoice) {
                $statement->lines()->firstOrCreate(
                    ['membership_invoice_id' => $invoice->id],
                    [
                        'gross_cents' => (int) $invoice->amount_cents,
                        'fee_cents' => (int) $invoice->platform_fee_cents,
                        'fee_vat_cents' => (int) $invoice->platform_fee_vat_cents,
                        'paid_at' => $invoice->paid_at,
                    ],
                );
            }

            $statement->forceFill([
                'total_gross_cents' => (int) $statement->lines()->sum('gross_cents'),
                'total_fee_cents' => (int) $statement->lines()->sum('fee_cents'),
                'total_vat_cents' => (int) $statement->lines()->sum('fee_vat_cents'),
                'issued_at' => $statement->issued_at ?? now(),
            ])->save();

            return $statement->fresh(['lines']);
        });
    }
}
