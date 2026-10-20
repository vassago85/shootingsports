<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Pages\Inbox;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\MdRequestApproved;
use App\Notifications\MdRequestRejected;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Mint an MD-signup enquiry linked to a user who is MD-pending (same
 * shape the public /directors/register flow produces). Returns the
 * [enquiry, applicant] pair so each test can act and assert.
 *
 * @return array{0: Enquiry, 1: User}
 */
function makePendingDirectorRequest(): array
{
    $applicant = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
        'name' => 'Dani Director',
        'email' => 'dani-'.uniqid().'@example.com',
    ]);

    $enquiry = Enquiry::create([
        'type' => EnquiryType::MdSignup,
        'user_id' => $applicant->id,
        'name' => $applicant->name,
        'email' => $applicant->email,
        'subject' => 'Match director signup',
        'body' => 'I want to list matches for Our Club.',
        'status' => EnquiryStatus::New,
    ]);

    return [$enquiry, $applicant];
}

it('approves a director request from the Inbox tab, grants /desk, closes the enquiry and emails the applicant', function (): void {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    [$enquiry, $applicant] = makePendingDirectorRequest();

    Livewire::actingAs($staff)
        ->test(Inbox::class, ['activeTab' => 'director_requests'])
        ->callTableAction('approveMd', $enquiry);

    $applicant->refresh();
    $enquiry->refresh();

    expect($applicant->is_match_director)->toBeTrue()
        ->and($applicant->md_approved_at)->not->toBeNull()
        ->and($enquiry->status)->toBe(EnquiryStatus::Closed);

    Notification::assertSentTo($applicant, MdRequestApproved::class);
});

it('rejects a director request from the Inbox tab with an optional reason', function (): void {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    [$enquiry, $applicant] = makePendingDirectorRequest();

    Livewire::actingAs($staff)
        ->test(Inbox::class, ['activeTab' => 'director_requests'])
        ->callTableAction('rejectMd', $enquiry, [
            'reason' => 'Could not verify link to the named club.',
        ]);

    $applicant->refresh();
    $enquiry->refresh();

    expect($applicant->is_match_director)->toBeFalse()
        ->and($applicant->md_rejected_at)->not->toBeNull()
        ->and($applicant->md_rejection_reason)->toBe('Could not verify link to the named club.')
        ->and($enquiry->status)->toBe(EnquiryStatus::Closed);

    Notification::assertSentTo(
        $applicant,
        MdRequestRejected::class,
        fn (MdRequestRejected $notification): bool => $notification->reason === 'Could not verify link to the named club.',
    );
});

it('rejection stores null (not an empty string) when no reason is supplied', function (): void {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    [$enquiry, $applicant] = makePendingDirectorRequest();

    Livewire::actingAs($staff)
        ->test(Inbox::class, ['activeTab' => 'director_requests'])
        ->callTableAction('rejectMd', $enquiry, ['reason' => '  ']);

    expect($applicant->refresh()->md_rejection_reason)->toBeNull();
});

it('hides the approve action for a request whose applicant is already approved', function (): void {
    $staff = User::factory()->staff()->create();
    [$enquiry, $applicant] = makePendingDirectorRequest();

    $applicant->forceFill([
        'is_match_director' => true,
        'md_approved_at' => now(),
    ])->save();

    // Status still "New" so the enquiry is in the queue, but the
    // applicant is already approved — the row should not offer
    // Approve or Reject.
    Livewire::actingAs($staff)
        ->test(Inbox::class, ['activeTab' => 'director_requests'])
        ->assertTableActionHidden('approveMd', $enquiry)
        ->assertTableActionHidden('rejectMd', $enquiry);
});
