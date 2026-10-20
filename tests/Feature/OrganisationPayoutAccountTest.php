<?php

use App\Enums\PayoutStatus;
use App\Models\Organisation;
use App\Models\OrganisationBalanceEntry;
use App\Models\OrganisationPayoutAccount;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('encrypts bank and document numbers and only exposes the last four', function () {
    $account = OrganisationPayoutAccount::factory()->create([
        'account_number' => '0123456789',
        'document_number' => '2012/123456/07',
    ]);

    $storedAccountNumber = DB::table('organisation_payout_accounts')->where('id', $account->id)->value('account_number');
    $storedDocumentNumber = DB::table('organisation_payout_accounts')->where('id', $account->id)->value('document_number');

    $fresh = $account->fresh();

    expect($storedAccountNumber)->not->toBe('0123456789')
        ->and($storedDocumentNumber)->not->toBe('2012/123456/07')
        ->and($fresh->account_number)->toBe('0123456789')
        ->and($fresh->document_number)->toBe('2012/123456/07')
        ->and($fresh->account_last_four)->toBe('6789')
        ->and($fresh->maskedLabel())->toBe('ABSA Bank ••••6789')
        ->and($fresh->toArray())->not->toHaveKeys(['account_number', 'document_number']);
});

it('logs who changed the account and only the old and new last four', function () {
    $account = OrganisationPayoutAccount::factory()->active()->create([
        'account_number' => '0123456789',
    ]);
    $user = User::factory()->create();

    $change = $account->recordChange(
        user: $user,
        oldLastFour: '6789',
        newLastFour: '4321',
        oldBankName: 'ABSA Bank',
        newBankName: 'FNB',
        oldAccountHolder: 'Old Club',
        newAccountHolder: 'New Club',
        oldStatus: PayoutStatus::Active,
        newStatus: PayoutStatus::PendingApproval,
    );

    expect($change->user_id)->toBe($user->id)
        ->and($change->old_last_four)->toBe('6789')
        ->and($change->new_last_four)->toBe('4321')
        ->and($change->created_at)->not->toBeNull()
        ->and($change->toArray())->not->toHaveKey('account_number');
});

it('lets only an active subaccount take online match payments', function () {
    $active = Organisation::factory()->create();
    OrganisationPayoutAccount::factory()->active()->create([
        'organisation_id' => $active->id,
    ]);

    $pending = Organisation::factory()->create();
    OrganisationPayoutAccount::factory()->pendingApproval()->create([
        'organisation_id' => $pending->id,
    ]);

    $draft = Organisation::factory()->create();

    expect($active->acceptsOnlineMatchPayments())->toBeTrue()
        ->and($pending->fresh()->acceptsOnlineMatchPayments())->toBeFalse()
        ->and($draft->acceptsOnlineMatchPayments())->toBeFalse()
        ->and($pending->payoutAccount->status->deskMessage())->toBe('Banking details received. Payouts start once approved.');
});

it('finds payout accounts that have waited past the approval window', function () {
    $this->freezeTime();

    $stale = OrganisationPayoutAccount::factory()->pendingApproval()->create([
        'submitted_at' => now()->subHours(49),
    ]);
    OrganisationPayoutAccount::factory()->pendingApproval()->create([
        'submitted_at' => now()->subHours(47),
    ]);

    expect(OrganisationPayoutAccount::query()->stalePendingApproval()->pluck('id')->all())
        ->toBe([$stale->id]);
});

it('sums what a club owes the platform', function () {
    $organisation = Organisation::factory()->create();

    OrganisationBalanceEntry::factory()->create([
        'organisation_id' => $organisation->id,
        'amount_cents' => 5000,
    ]);
    OrganisationBalanceEntry::factory()->create([
        'organisation_id' => $organisation->id,
        'amount_cents' => -2000,
    ]);

    expect($organisation->amountOwedToPlatformCents())->toBe(3000);
});

it('hides the raw paystack payload on a payment', function () {
    $payment = Payment::factory()->create([
        'raw_response' => ['status' => 'success', 'reference' => 'ss_hidden'],
    ]);

    $stored = DB::table('payments')->where('id', $payment->id)->value('raw_response');

    expect($stored)->not->toContain('ss_hidden')
        ->and($payment->fresh()->raw_response['reference'])->toBe('ss_hidden')
        ->and($payment->fresh()->toArray())->not->toHaveKey('raw_response');
});
