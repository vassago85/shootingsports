<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Livewire\Platform\EnablePaystackSubaccount;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Platform\AgencyAgreement;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function attachApprovedAdmin(Organisation $org, User $user): void
{
    $org->users()->attach($user->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);
}

it('refuses to create the subaccount until the agreement is accepted', function () {
    Http::fake();

    $org = Organisation::factory()->create();
    $user = User::factory()->create();
    attachApprovedAdmin($org, $user);
    $this->actingAs($user);

    Livewire::test(EnablePaystackSubaccount::class, ['organisation' => $org])
        ->set('settlement_bank', '058')
        ->set('account_number', '0123456789')
        ->set('business_name', 'Test Club')
        ->call('submit')
        ->assertHasErrors('agreement_accepted');

    Http::assertNothingSent();
    expect($org->refresh()->paystack_subaccount_id)->toBeNull();
});

it('creates the subaccount via Paystack once agreement is accepted', function () {
    Http::fake([
        'api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => ['subaccount_code' => 'ACCT_test123', 'is_verified' => false],
        ], 201),
    ]);

    $org = Organisation::factory()->create();
    $user = User::factory()->create();
    attachApprovedAdmin($org, $user);
    $this->actingAs($user);

    Livewire::test(EnablePaystackSubaccount::class, ['organisation' => $org])
        ->set('agreement_accepted', true)
        ->set('settlement_bank', '058')
        ->set('account_number', '0123456789')
        ->set('business_name', 'Test Club')
        ->call('submit')
        ->assertHasNoErrors();

    $org->refresh();
    expect($org->paystack_subaccount_id)->toBe('ACCT_test123')
        ->and($org->paystack_subaccount_status)->toBe('pending')
        ->and($org->agency_agreement_version)->toBe(AgencyAgreement::currentVersion())
        ->and($org->agency_agreement_signed_at)->not->toBeNull()
        ->and($org->hasSignedCurrentAgencyAgreement())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/subaccount'
        && $request['business_name'] === 'Test Club'
        && $request['settlement_bank'] === '058'
        && $request['account_number'] === '0123456789');
});

it('forbids users who are not approved admins or secretaries of the organisation', function () {
    $org = Organisation::factory()->create();
    $outsider = User::factory()->create();
    $this->actingAs($outsider);

    Livewire::test(EnablePaystackSubaccount::class, ['organisation' => $org])
        ->assertForbidden();
});
