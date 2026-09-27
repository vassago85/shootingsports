<?php

use App\Enums\ClaimStatus;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Mail\EnquiryReceivedMail;
use App\Mail\ListingClaimSubmittedMail;
use App\Mail\NewRegistrationMail;
use App\Mail\ProTrialEndingSoon;
use App\Models\Claim;
use App\Models\Enquiry;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\MdRequestApproved;
use Illuminate\Auth\Notifications\VerifyEmail;

beforeEach(function (): void {
    $this->withoutVite();
});

it('sends html mail with the logo', function () {
    $user = User::factory()->create(['name' => 'Sam Shooter']);
    $club = Organisation::factory()->create([
        'name' => 'Claimable Club',
        'status' => ListingStatus::Published,
    ]);
    $claim = Claim::query()->create([
        'claimable_type' => 'organisation',
        'claimable_id' => $club->id,
        'user_id' => $user->id,
        'evidence' => 'I am the club secretary and the phone number on the page is ours.',
        'status' => ClaimStatus::Pending,
    ]);
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'subject' => 'List our club',
        'body' => 'Please tell me how to list our club.',
        'status' => EnquiryStatus::New,
    ]);

    $messages = [
        (string) (new NewRegistrationMail($user, ['shooter']))->render(),
        (string) (new ListingClaimSubmittedMail($claim, $club->name, 'club'))->render(),
        (string) (new EnquiryReceivedMail($enquiry))->render(),
        (string) (new ProTrialEndingSoon($user))->render(),
        (string) (new MdRequestApproved($user))->toMail($user)->render(),
        (string) (new VerifyEmail)->toMail($user)->render(),
    ];

    foreach ($messages as $html) {
        expect($html)
            ->toContain('<!DOCTYPE html')
            ->toContain('logo-lockup.png')
            ->toContain('#0B0D0E');
    }

    expect($messages[1])->toContain('Claimable Club')
        ->and($messages[4])->toContain('#6B7D3A');
});
