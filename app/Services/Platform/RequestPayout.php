<?php

namespace App\Services\Platform;

use App\Models\ClubPayable;
use App\Models\Organisation;
use App\Models\PlatformPayout;
use App\Support\PlatformFees;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rolls a club's outstanding {@see ClubPayable} rows into one
 * {@see PlatformPayout}. The first payout in a calendar month is free;
 * every later one carries the (VAT-able) payout admin fee.
 */
class RequestPayout
{
    /**
     * @throws RuntimeException when the club has nothing outstanding.
     */
    public function for(Organisation $organisation): PlatformPayout
    {
        return DB::transaction(function () use ($organisation): PlatformPayout {
            $payables = ClubPayable::query()
                ->where('organisation_id', $organisation->id)
                ->where('paid_out', false)
                ->lockForUpdate()
                ->get();

            if ($payables->isEmpty()) {
                throw new RuntimeException('No payables to pay out for '.$organisation->name);
            }

            $subtotal = (int) $payables->sum('net_due_cents');

            $alreadyPaidThisMonth = PlatformPayout::query()
                ->where('organisation_id', $organisation->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->exists();

            $adminFee = $alreadyPaidThisMonth ? (int) config('platform.payout_admin_fee_cents', 0) : 0;
            $adminFeeVat = $adminFee > 0 ? PlatformFees::feeVatCents($adminFee) : 0;

            $payout = PlatformPayout::create([
                'organisation_id' => $organisation->id,
                'subtotal_cents' => $subtotal,
                'admin_fee_cents' => $adminFee,
                'admin_fee_vat_cents' => $adminFeeVat,
                'net_cents' => max(0, $subtotal - $adminFee - $adminFeeVat),
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $payables->each->update(['platform_payout_id' => $payout->id, 'paid_out' => true]);

            return $payout->fresh('payables');
        });
    }
}
