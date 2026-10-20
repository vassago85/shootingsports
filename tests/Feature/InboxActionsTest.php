<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Enquiry;
use App\Models\Submission;
use App\Models\User;
use App\Support\Inbox\AgeLabel;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

// --- Submission reject --------------------------------------------------

it('rejects a pending submission from the Inbox with a stored reason', function (): void {
    $staff = User::factory()->staff()->create();
    $submission = Submission::create([
        'type' => 'organisation',
        'payload' => ['name' => 'Submitted Club'],
        'submitter_name' => 'Alice',
        'submitter_email' => 'alice@example.com',
        'status' => SubmissionStatus::Pending,
    ]);

    Livewire::actingAs($staff)
        ->test(ListSubmissions::class)
        ->callTableAction('reject', $submission, [
            'reason' => 'Duplicate of club #42 — merged there instead.',
        ]);

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Rejected)
        ->and($submission->moderator_id)->toBe($staff->id)
        ->and($submission->reject_reason)->toBe('Duplicate of club #42 — merged there instead.');
});

it('requires a reason to reject a submission', function (): void {
    $staff = User::factory()->staff()->create();
    $submission = Submission::create([
        'type' => 'organisation',
        'payload' => ['name' => 'Submitted Club'],
        'submitter_name' => 'Alice',
        'submitter_email' => 'alice@example.com',
        'status' => SubmissionStatus::Pending,
    ]);

    Livewire::actingAs($staff)
        ->test(ListSubmissions::class)
        ->callTableAction('reject', $submission, ['reason' => ''])
        ->assertHasActionErrors(['reason']);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Pending);
});

// --- Enquiry mark handled -----------------------------------------------

it('closes an enquiry in one click via the mark-handled action', function (): void {
    $staff = User::factory()->staff()->create();
    $enquiry = Enquiry::create([
        'type' => EnquiryType::General,
        'name' => 'Pending Person',
        'email' => 'pending@example.com',
        'subject' => 'Fixture',
        'body' => 'Fixture body',
        'status' => EnquiryStatus::New,
    ]);

    Livewire::actingAs($staff)
        ->test(ListEnquiries::class)
        ->callTableAction('mark_handled', $enquiry);

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Closed);
});

it('hides mark-handled once the enquiry is already closed', function (): void {
    $staff = User::factory()->staff()->create();
    $enquiry = Enquiry::create([
        'type' => EnquiryType::General,
        'name' => 'Already handled',
        'email' => 'ok@example.com',
        'subject' => 'Fixture',
        'body' => 'Fixture body',
        'status' => EnquiryStatus::Closed,
    ]);

    Livewire::actingAs($staff)
        ->test(ListEnquiries::class)
        ->assertTableActionHidden('mark_handled', $enquiry);
});

// --- Age label ----------------------------------------------------------

it('labels age in days with today, 1 day, and N days variants', function (): void {
    expect(AgeLabel::for(null))->toBe('—')
        ->and(AgeLabel::for(Carbon::now()->subHours(2)))->toBe('today')
        ->and(AgeLabel::for(Carbon::now()->subDay()->subHour()))->toBe('1 day')
        ->and(AgeLabel::for(Carbon::now()->subDays(5)))->toBe('5 days');
});
