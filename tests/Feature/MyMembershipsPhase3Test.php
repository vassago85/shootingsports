<?php

use App\Enums\MembershipInvoiceStatus;
use App\Livewire\MyMembershipDetails;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\User;
use Livewire\Livewire;

it('redirects a guest hitting /my-memberships to login', function () {
    $response = $this->get('/my-memberships');

    $response->assertRedirect(route('login'));
});

it('shows only the signed-in users memberships', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $organisation = Organisation::factory()->create();

    $mine = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'first_name' => 'Alpha',
        'last_name' => 'Mine',
    ]);
    $notMine = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $other->id,
        'first_name' => 'Zulu',
        'last_name' => 'NotMine',
    ]);

    $this->actingAs($user);

    $response = $this->get('/my-memberships');
    $response->assertOk();
    $response->assertSee('Alpha Mine');
    $response->assertDontSee('Zulu NotMine');
});

it('also matches memberships by email when user_id is not linked yet', function () {
    $user = User::factory()->create(['email' => 'carol@example.test']);
    $organisation = Organisation::factory()->create();

    ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'user_id' => null,
        'email' => 'carol@example.test',
        'first_name' => 'Carol',
        'last_name' => 'ByEmail',
    ]);

    $this->actingAs($user);

    $this->get('/my-memberships')->assertOk()->assertSee('Carol ByEmail');
});

it('prevents viewing another users membership detail (403)', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherMember = ClubMember::factory()->active()->create([
        'user_id' => $other->id,
        'email' => $other->email,
    ]);

    $this->actingAs($user);

    $this->get(route('my-memberships.show', $otherMember))->assertForbidden();
});

it('lets the member save contact details on the detail page', function () {
    $user = User::factory()->create();
    $member = ClubMember::factory()->active()->create([
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    $this->actingAs($user);

    Livewire::test(MyMembershipDetails::class, ['member' => $member])
        ->set('phone', '+27 11 123 4567')
        ->set('emergencyContactName', 'Jane Doe')
        ->set('emergencyContactPhone', '+27 11 999 0000')
        ->call('updateContact')
        ->assertHasNoErrors();

    $member->refresh();
    expect($member->phone)->toBe('+27 11 123 4567')
        ->and($member->emergency_contact_name)->toBe('Jane Doe')
        ->and($member->emergency_contact_phone)->toBe('+27 11 999 0000');
});

it('lets the member notify EFT paid which stamps the pending-confirmation column but does not mark Paid', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'email' => $user->email,
    ]);
    $invoice = MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'club_member_id' => $member->id,
        'status' => MembershipInvoiceStatus::Issued,
    ]);

    $this->actingAs($user);

    Livewire::test(MyMembershipDetails::class, ['member' => $member])
        ->call('notifyPaidByEft', $invoice->id);

    $invoice->refresh();
    expect($invoice->status)->toBe(MembershipInvoiceStatus::Issued)
        ->and($invoice->payment_pending_confirmation_at)->not->toBeNull();
});

it('ignores notifyPaidByEft for an invoice belonging to someone else', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $myMember = ClubMember::factory()->active()->create([
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    $otherMember = ClubMember::factory()->active()->create([
        'user_id' => $someoneElse->id,
        'email' => $someoneElse->email,
    ]);
    $otherInvoice = MembershipInvoice::factory()->create([
        'organisation_id' => $otherMember->organisation_id,
        'club_member_id' => $otherMember->id,
        'status' => MembershipInvoiceStatus::Issued,
    ]);

    $this->actingAs($user);

    Livewire::test(MyMembershipDetails::class, ['member' => $myMember])
        ->call('notifyPaidByEft', $otherInvoice->id);

    $otherInvoice->refresh();
    expect($otherInvoice->payment_pending_confirmation_at)->toBeNull();
});

it('refuses the paystack init when the club does not accept online payments', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create(['membership_online_payments' => false]);
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'email' => $user->email,
    ]);
    $invoice = MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'club_member_id' => $member->id,
    ]);

    $this->actingAs($user);

    // CSRF middleware trips the Pest HTTP client — pass a valid token
    // so the real auth + guard path runs end-to-end instead of mocking
    // it out with withoutMiddleware (which also drops session, which
    // the test needs for assertSessionHas).
    $this->startSession();

    $this->post(route('my-memberships.paystack', $invoice), [
        '_token' => csrf_token(),
    ])
        ->assertRedirect(route('my-memberships'))
        ->assertSessionHas('status', 'This club is not accepting online payments right now.');
});
