<?php

use App\Enums\MembershipInvoiceStatus;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\PlatformFeeLine;
use App\Models\PlatformFeeStatement;
use App\Services\Platform\CompileFeeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

function feeStatementPaidInvoice(Organisation $organisation, string $paidAt, array $overrides = []): MembershipInvoice
{
    return MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'status' => MembershipInvoiceStatus::Paid,
        'paid_at' => $paidAt,
        'amount_cents' => 60000,
        'platform_fee_cents' => 1200,
        'platform_fee_vat_cents' => 180,
        'gateway_fee_cents' => 1500,
        ...$overrides,
    ]);
}

function feeStatementOctoberStart(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 10, 1)->startOfMonth();
}

it('compiles paid invoices in the period into a statement with one line per invoice', function (): void {
    $organisation = Organisation::factory()->create();

    feeStatementPaidInvoice($organisation, '2026-10-01 00:00:00');
    feeStatementPaidInvoice($organisation, '2026-10-31 23:59:59', ['amount_cents' => 30000, 'platform_fee_cents' => 600, 'platform_fee_vat_cents' => 90]);
    feeStatementPaidInvoice($organisation, '2026-09-30 23:59:59');
    feeStatementPaidInvoice($organisation, '2026-11-01 00:00:00');
    MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'status' => MembershipInvoiceStatus::Issued,
        'platform_fee_cents' => 1200,
        'platform_fee_vat_cents' => 180,
    ]);

    $statement = app(CompileFeeStatement::class)->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    expect($statement->lines)->toHaveCount(2)
        ->and($statement->total_gross_cents)->toBe(90000)
        ->and($statement->total_fee_cents)->toBe(1800)
        ->and($statement->total_vat_cents)->toBe(270)
        ->and($statement->issued_at)->not->toBeNull()
        ->and($statement->period_start->toDateString())->toBe('2026-10-01')
        ->and($statement->period_end->toDateString())->toBe('2026-10-31');
});

it('is idempotent — running twice produces the same statement with the same lines', function (): void {
    $organisation = Organisation::factory()->create();
    feeStatementPaidInvoice($organisation, '2026-10-05 10:00:00');
    feeStatementPaidInvoice($organisation, '2026-10-20 10:00:00');

    $compiler = app(CompileFeeStatement::class);
    $first = $compiler->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());
    $second = $compiler->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    expect(PlatformFeeStatement::count())->toBe(1)
        ->and(PlatformFeeLine::count())->toBe(2)
        ->and($second->id)->toBe($first->id)
        ->and($second->total_fee_cents)->toBe(2400)
        ->and($second->total_vat_cents)->toBe(360)
        ->and($second->total_gross_cents)->toBe(120000);
});

it('picks up invoices paid after an earlier compile without duplicating existing lines', function (): void {
    $organisation = Organisation::factory()->create();
    feeStatementPaidInvoice($organisation, '2026-10-05 10:00:00');

    $compiler = app(CompileFeeStatement::class);
    $compiler->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    feeStatementPaidInvoice($organisation, '2026-10-06 10:00:00');
    $statement = $compiler->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    expect(PlatformFeeLine::count())->toBe(2)
        ->and($statement->total_fee_cents)->toBe(2400);
});

it('ignores invoices whose platform_fee_cents is zero but still writes an empty statement', function (): void {
    $organisation = Organisation::factory()->create();
    feeStatementPaidInvoice($organisation, '2026-10-10 10:00:00', ['platform_fee_cents' => 0, 'platform_fee_vat_cents' => 0]);

    $statement = app(CompileFeeStatement::class)->for($organisation, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    expect(PlatformFeeStatement::count())->toBe(1)
        ->and(PlatformFeeLine::count())->toBe(0)
        ->and($statement->total_fee_cents)->toBe(0)
        ->and($statement->total_vat_cents)->toBe(0)
        ->and($statement->total_gross_cents)->toBe(0);
});

it('scopes correctly per organisation', function (): void {
    $first = Organisation::factory()->create();
    $second = Organisation::factory()->create();
    feeStatementPaidInvoice($first, '2026-10-10 10:00:00');
    feeStatementPaidInvoice($second, '2026-10-12 10:00:00', ['platform_fee_cents' => 2000, 'platform_fee_vat_cents' => 300]);

    $compiler = app(CompileFeeStatement::class);
    $firstStatement = $compiler->for($first, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());
    $secondStatement = $compiler->for($second, feeStatementOctoberStart(), feeStatementOctoberStart()->endOfMonth());

    expect(PlatformFeeStatement::count())->toBe(2)
        ->and($firstStatement->total_fee_cents)->toBe(1200)
        ->and($secondStatement->total_fee_cents)->toBe(2000)
        ->and($secondStatement->lines)->toHaveCount(1);
});

it('the command runs for all organisations with paid invoices in the previous month by default', function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 15, 12));

    $withInvoice = Organisation::factory()->create();
    $withoutInvoice = Organisation::factory()->create();
    feeStatementPaidInvoice($withInvoice, '2026-10-14 09:00:00');
    feeStatementPaidInvoice($withoutInvoice, '2026-11-02 09:00:00');

    $exitCode = Artisan::call('platform:compile-monthly-fee-statements');

    $statement = PlatformFeeStatement::sole();

    expect($exitCode)->toBe(0)
        ->and($statement->organisation_id)->toBe($withInvoice->id)
        ->and($statement->period_start->toDateString())->toBe('2026-10-01')
        ->and($statement->period_end->toDateString())->toBe('2026-10-31')
        ->and($statement->lines)->toHaveCount(1)
        ->and(Artisan::output())->toContain("Compiled {$statement->id} for {$withInvoice->name}: fee=1200 vat=180 gross=60000");
});

it('the command honours the --month flag', function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 15, 12));

    $organisation = Organisation::factory()->create();
    feeStatementPaidInvoice($organisation, '2026-09-14 09:00:00');
    feeStatementPaidInvoice($organisation, '2026-10-14 09:00:00');

    Artisan::call('platform:compile-monthly-fee-statements', ['--month' => '2026-09']);

    $statement = PlatformFeeStatement::sole();

    expect($statement->period_start->toDateString())->toBe('2026-09-01')
        ->and($statement->period_end->toDateString())->toBe('2026-09-30')
        ->and($statement->lines)->toHaveCount(1);
});

it('the command honours the --org flag', function (): void {
    $first = Organisation::factory()->create();
    $second = Organisation::factory()->create();
    feeStatementPaidInvoice($first, '2026-10-14 09:00:00');
    feeStatementPaidInvoice($second, '2026-10-14 09:00:00');

    Artisan::call('platform:compile-monthly-fee-statements', ['--month' => '2026-10', '--org' => $second->id]);

    expect(PlatformFeeStatement::count())->toBe(1)
        ->and(PlatformFeeStatement::sole()->organisation_id)->toBe($second->id);
});

it('the command skips organisations whose only paid invoices carry no platform fee', function (): void {
    $organisation = Organisation::factory()->create();
    feeStatementPaidInvoice($organisation, '2026-10-14 09:00:00', ['platform_fee_cents' => 0, 'platform_fee_vat_cents' => 0]);

    Artisan::call('platform:compile-monthly-fee-statements', ['--month' => '2026-10']);

    expect(PlatformFeeStatement::count())->toBe(0);
});
