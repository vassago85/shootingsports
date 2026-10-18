<?php

namespace App\Services\Platform;

use App\Models\PlatformInvoice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Allocates sequential `INV-{year}-{000000}` numbers for platform tax
 * invoices. A unique violation from a concurrent writer bumps the
 * counter and retries.
 */
class PlatformInvoiceNumbers
{
    private const ATTEMPTS = 3;

    /**
     * @param  array<string, mixed>  $attributes  everything except `invoice_number`
     */
    public function createInvoice(array $attributes, int $year): PlatformInvoice
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(fn (): PlatformInvoice => PlatformInvoice::query()->create([
                    ...$attributes,
                    'invoice_number' => $this->next($year, $attempt),
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempt >= self::ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    public function next(int $year, int $skip = 0): string
    {
        $latest = PlatformInvoice::query()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->max('invoice_number');

        $sequence = $latest === null ? 0 : (int) substr((string) $latest, -6);

        return sprintf('INV-%d-%06d', $year, $sequence + 1 + $skip);
    }
}
