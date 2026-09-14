<?php

use App\Enums\EventLevel;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Organisation;

it('maps listing status colours from the real enum cases', function () {
    expect(ListingStatus::Published->getColor())->toBe('success')
        ->and(ListingStatus::Pending->getColor())->toBe('warning')
        ->and(ListingStatus::Archived->getColor())->toBe('danger');
});

it('maps event level colours from the real enum cases', function () {
    expect(EventLevel::Club->getColor())->toBe('gray')
        ->and(EventLevel::Series->getColor())->toBe('warning')
        ->and(EventLevel::Provincial->getColor())->toBe('info')
        ->and(EventLevel::National->getColor())->toBe('primary')
        ->and(EventLevel::International->getColor())->toBe('success');
});

it('maps organisation type colours from the real enum cases', function () {
    expect(OrganisationType::Club->getColor())->toBe('info')
        ->and(OrganisationType::Series->getColor())->toBe('warning')
        ->and(OrganisationType::Federation->getColor())->toBe('primary')
        ->and(OrganisationType::Association->getColor())->toBe('teal')
        ->and(OrganisationType::ProvincialBody->getColor())->toBe('cyan');
});

it('keeps unconfirmed verification muted so verified is the only success glow', function () {
    expect(VerificationState::Verified->getColor())->toBe('success')
        ->and(VerificationState::Unconfirmed->getColor())->toBe('gray')
        ->and(VerificationState::Ageing->getColor())->toBe('warning')
        ->and(VerificationState::Archived->getColor())->toBe('danger');
});

it('formats location as town, province and builds an initials avatar', function () {
    $organisation = new Organisation([
        'name' => 'Pretoria Precision Rifle Club',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
    ]);

    expect($organisation->locationLabel())->toBe('Pretoria, Gauteng')
        ->and($organisation->avatarInitials())->toBe('PP')
        ->and($organisation->initialsAvatarDataUri())->toStartWith('data:image/svg+xml');
});
