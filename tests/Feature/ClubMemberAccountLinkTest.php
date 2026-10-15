<?php

use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

it('links unlinked members with the verified email', function () {
    $club = Organisation::factory()->create();
    $imported = ClubMember::factory()
        ->for($club, 'organisation')
        ->create(['user_id' => null, 'email' => 'Jana@Example.COM']);

    $otherClub = Organisation::factory()->create();
    ClubMember::factory()
        ->for($otherClub, 'organisation')
        ->create(['user_id' => null, 'email' => 'someone-else@example.com']);

    $user = User::factory()->create(['email' => 'jana@example.com']);

    event(new Verified($user));

    expect($imported->fresh()->user_id)->toBe($user->id)
        ->and(ClubMember::query()->where('email', 'someone-else@example.com')->sole()->user_id)->toBeNull();
});

it('leaves already-linked members alone', function () {
    $club = Organisation::factory()->create();
    $other = User::factory()->create();

    $member = ClubMember::factory()
        ->for($club, 'organisation')
        ->create(['user_id' => $other->id, 'email' => 'jana@example.com']);

    $newUser = User::factory()->create(['email' => 'jana@example.com']);

    event(new Verified($newUser));

    expect($member->fresh()->user_id)->toBe($other->id);
});
