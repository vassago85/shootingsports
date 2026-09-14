<?php

use App\Enums\VerificationState;
use App\Models\Organisation;

it('marks a listing verified and stamps last_verified_at', function () {
    $organisation = Organisation::factory()->create([
        'verification_state' => VerificationState::Unconfirmed,
        'last_verified_at' => null,
    ]);

    $organisation->markVerified();
    $organisation->refresh();

    expect($organisation->verification_state)->toBe(VerificationState::Verified)
        ->and($organisation->last_verified_at)->not->toBeNull();
});

it('moves a stale verified listing to ageing', function () {
    $organisation = Organisation::factory()->create([
        'verification_state' => VerificationState::Verified,
        'last_verified_at' => now()->subDays(Organisation::AGEING_AFTER_DAYS + 1),
    ]);

    $organisation->refreshVerificationState();
    $organisation->refresh();

    expect($organisation->verification_state)->toBe(VerificationState::Ageing)
        ->and($organisation->isStale())->toBeTrue();
});

it('does not auto-change an archived verification state', function () {
    $organisation = Organisation::factory()->create([
        'verification_state' => VerificationState::Archived,
        'last_verified_at' => now()->subYear(),
    ]);

    $organisation->refreshVerificationState();
    $organisation->refresh();

    expect($organisation->verification_state)->toBe(VerificationState::Archived);
});

it('treats a never-verified listing as unconfirmed after refresh', function () {
    $organisation = Organisation::factory()->create([
        'verification_state' => VerificationState::Verified,
        'last_verified_at' => null,
    ]);

    $organisation->refreshVerificationState();
    $organisation->refresh();

    expect($organisation->verification_state)->toBe(VerificationState::Unconfirmed)
        ->and($organisation->verification_token)->not->toBeNull();
});
