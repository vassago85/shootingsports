<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\MdRequestApproved;
use App\Notifications\MdRequestRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

// ---- mdStatus() state machine -------------------------------------

it('mdStatus() returns "none" for a fresh shooter', function () {
    $user = User::factory()->create();

    expect($user->mdStatus())->toBe('none')
        ->and($user->isMdPending())->toBeFalse()
        ->and($user->isMdRejected())->toBeFalse();
});

it('mdStatus() returns "pending" after signup but before approval', function () {
    $user = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);

    expect($user->mdStatus())->toBe('pending')
        ->and($user->isMdPending())->toBeTrue();
});

it('mdStatus() returns "approved" for an approved MD', function () {
    $user = User::factory()->create([
        'is_match_director' => true,
        'md_requested_at' => now(),
        'md_approved_at' => now(),
    ]);

    expect($user->mdStatus())->toBe('approved')
        ->and($user->isMdPending())->toBeFalse();
});

it('mdStatus() returns "rejected" for a rejected applicant', function () {
    $user = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
        'md_rejected_at' => now(),
    ]);

    expect($user->mdStatus())->toBe('rejected')
        ->and($user->isMdRejected())->toBeTrue()
        ->and($user->isMdPending())->toBeFalse();
});

// ---- Approve action (via Filament) --------------------------------

it('approveMdRequest flips is_match_director, stamps approved_at, closes enquiry, emails applicant', function () {
    Notification::fake();

    $applicant = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);
    $staff = User::factory()->staff()->create();

    $enquiry = Enquiry::create([
        'type' => EnquiryType::MdSignup,
        'user_id' => $applicant->id,
        'name' => $applicant->name,
        'email' => $applicant->email,
        'subject' => 'MD request: '.$applicant->name,
        'body' => 'Pretoria RC',
        'status' => EnquiryStatus::New,
    ]);

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->callTableAction('approveMdRequest', $applicant);

    $applicant->refresh();

    expect($applicant->is_match_director)->toBeTrue()
        ->and($applicant->md_approved_at)->not->toBeNull()
        ->and($applicant->md_rejected_at)->toBeNull();

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Closed);

    Notification::assertSentTo($applicant, MdRequestApproved::class);
});

it('approveMdRequest on a previously-rejected user clears the rejection state', function () {
    Notification::fake();

    $applicant = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
        'md_rejected_at' => now(),
        'md_rejection_reason' => 'first time round we could not verify',
    ]);
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->callTableAction('approveMdRequest', $applicant);

    $applicant->refresh();

    expect($applicant->is_match_director)->toBeTrue()
        ->and($applicant->md_rejected_at)->toBeNull()
        ->and($applicant->md_rejection_reason)->toBeNull()
        ->and($applicant->md_approved_at)->not->toBeNull();
});

// ---- Reject action ------------------------------------------------

it('rejectMdRequest stamps rejected_at, saves the reason, closes enquiry, emails applicant', function () {
    Notification::fake();

    $applicant = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);
    $staff = User::factory()->staff()->create();

    $enquiry = Enquiry::create([
        'type' => EnquiryType::MdSignup,
        'user_id' => $applicant->id,
        'name' => $applicant->name,
        'email' => $applicant->email,
        'subject' => 'MD request: '.$applicant->name,
        'body' => 'not enough info',
        'status' => EnquiryStatus::New,
    ]);

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->callTableAction('rejectMdRequest', $applicant, [
            'reason' => 'Could not verify link to the named club.',
        ]);

    $applicant->refresh();

    expect($applicant->is_match_director)->toBeFalse()
        ->and($applicant->md_rejected_at)->not->toBeNull()
        ->and($applicant->md_rejection_reason)->toBe('Could not verify link to the named club.');

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Closed);

    Notification::assertSentTo(
        $applicant,
        MdRequestRejected::class,
        fn (MdRequestRejected $n) => $n->reason === 'Could not verify link to the named club.',
    );
});

it('rejectMdRequest accepts an empty reason (still notifies)', function () {
    Notification::fake();

    $applicant = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->callTableAction('rejectMdRequest', $applicant, ['reason' => '']);

    $applicant->refresh();

    expect($applicant->md_rejection_reason)->toBeNull()
        ->and($applicant->md_rejected_at)->not->toBeNull();

    Notification::assertSentTo(
        $applicant,
        MdRequestRejected::class,
        fn (MdRequestRejected $n) => $n->reason === null,
    );
});

// ---- Action visibility --------------------------------------------

it('approve action is hidden for a user who never applied to be an MD', function () {
    $regular = User::factory()->create();
    $staff = User::factory()->staff()->create();

    expect($regular->isMdPending())->toBeFalse()
        ->and($regular->isMdRejected())->toBeFalse();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->assertTableActionHidden('approveMdRequest', $regular)
        ->assertTableActionHidden('rejectMdRequest', $regular);
});

it('reject action is hidden for an already-approved MD', function () {
    $approved = User::factory()->matchDirector()->create([
        'md_requested_at' => now(),
        'md_approved_at' => now(),
    ]);
    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(ListUsers::class)
        ->assertTableActionHidden('rejectMdRequest', $approved);
});

// ---- Notification content ------------------------------------------

it('the approval mail addresses the applicant by name and links to /desk', function () {
    $applicant = User::factory()->create(['name' => 'Testy McApprover']);

    $mail = (new MdRequestApproved($applicant))->toMail($applicant);
    $rendered = (string) $mail->render();

    expect($rendered)->toContain('Testy McApprover')
        ->and($rendered)->toContain('/desk')
        ->and($rendered)->toContain('approved');
});

it('the rejection mail includes the reason when provided and omits it when null', function () {
    $applicant = User::factory()->create(['name' => 'Testy McRejected']);

    $withReason = (string) (new MdRequestRejected($applicant, 'not verified'))->toMail($applicant)->render();
    expect($withReason)->toContain('Testy McRejected')
        ->and($withReason)->toContain('not verified');

    $withoutReason = (string) (new MdRequestRejected($applicant))->toMail($applicant)->render();
    expect($withoutReason)->toContain('Testy McRejected')
        ->and($withoutReason)->not->toContain('Reviewer note:');
});

// ---- Migration back-fill (grandfather) -----------------------------

it('grandfathers existing MDs so their md_approved_at is set to created_at', function () {
    // Create a user, then simulate the "pre-migration" state by nulling
    // both timestamps and re-running the same UPDATE the migration does.
    $legacy = User::factory()->matchDirector()->create([
        'created_at' => now()->subMonths(6),
    ]);

    DB::table('users')
        ->where('id', $legacy->id)
        ->update(['md_requested_at' => null, 'md_approved_at' => null]);

    // Re-run the same shape of back-fill query the migration used.
    DB::table('users')
        ->where('is_match_director', true)
        ->whereNull('md_requested_at')
        ->update([
            'md_requested_at' => DB::raw('created_at'),
            'md_approved_at' => DB::raw('created_at'),
        ]);

    $legacy->refresh();

    expect($legacy->md_requested_at)->not->toBeNull()
        ->and($legacy->md_approved_at)->not->toBeNull()
        ->and($legacy->md_requested_at->format('Y-m-d'))
        ->toBe($legacy->created_at->format('Y-m-d'));
});
