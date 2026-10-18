<?php

namespace App\Console\Commands;

use App\Enums\MembershipInvoiceStatus;
use App\Models\Organisation;
use App\Services\Platform\CompileFeeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Monthly job that compiles a platform fee statement for every
 * organisation that had fee-bearing paid membership invoices in the
 * target month (default: previous month). Idempotent and safe to
 * re-run; see {@see CompileFeeStatement}.
 *
 * Wired up in routes/console.php for 03:00 on the 1st of each month.
 */
#[AsCommand(name: 'platform:compile-monthly-fee-statements')]
class CompileMonthlyFeeStatements extends Command
{
    protected $signature = 'platform:compile-monthly-fee-statements
        {--month= : YYYY-MM, defaults to previous month in app timezone}
        {--org= : optional organisation id to compile for a single club}';

    protected $description = 'Compile monthly platform fee statements from paid membership invoices. Idempotent.';

    public function handle(CompileFeeStatement $compiler): int
    {
        $month = $this->option('month') ?? CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');

        try {
            $periodStart = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        } catch (Throwable) {
            $this->error("Invalid --month value [{$month}]; expected YYYY-MM.");

            return self::FAILURE;
        }

        $periodEnd = $periodStart->endOfMonth();

        $organisations = Organisation::query()
            ->whereHas('membershipInvoices', function (Builder $query) use ($periodStart, $periodEnd): void {
                $query->where('status', MembershipInvoiceStatus::Paid->value)
                    ->whereBetween('paid_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
                    ->where('platform_fee_cents', '>', 0);
            })
            ->when($this->option('org'), fn (Builder $query, mixed $organisationId) => $query->whereKey($organisationId))
            ->get();

        foreach ($organisations as $organisation) {
            $statement = $compiler->for($organisation, $periodStart, $periodEnd);

            $this->line("Compiled {$statement->id} for {$organisation->name}: fee={$statement->total_fee_cents} vat={$statement->total_vat_cents} gross={$statement->total_gross_cents}");
        }

        return self::SUCCESS;
    }
}
