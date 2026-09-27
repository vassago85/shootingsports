<?php

use App\Enums\ClaimStatus;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationUserRole;
use App\Livewire\Auth\Register;
use App\Livewire\Listings\SubmitClaim;
use App\Mail\ListingClaimSubmittedMail;
use App\Models\Claim;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event as EventFake;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    config()->set('registration.notify_emails', ['dirk@example.test']);
});

it('shows claim links on unclaimed club, range, and match pages', function () {
    $club = Organisation::factory()->create([
        'name' => 'Unclaimed Club',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);
    $venue = Venue::factory()->create([
        'name' => 'Unclaimed Range',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'title' => 'Unclaimed Match',
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
        'status' => EventStatus::Confirmed,
    ]);

    $this->get(route('clubs.show', $club))
        ->assertOk()
        ->assertSee('This is my club')
        ->assertSee(route('listings.claim', ['type' => 'club', 'slug' => $club->slug]), false);

    $this->get(route('ranges.show', $venue->slug))
        ->assertOk()
        ->assertSee('This is my range')
        ->assertSee(route('listings.claim', ['type' => 'range', 'slug' => $venue->slug]), false);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('This is my match')
        ->assertSee(route('listings.claim', ['type' => 'match', 'slug' => $event->slug]), false);
});

it('hides claim links once a club or range has an owner', function () {
    $owner = User::factory()->create();
    $club = Organisation::factory()->create([
        'claimed_by' => $owner->id,
        'status' => ListingStatus::Published,
    ]);
    $venue = Venue::factory()->create([
        'claimed_by' => $owner->id,
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
        'status' => EventStatus::Confirmed,
    ]);

    $this->get(route('clubs.show', $club))->assertOk()->assertDontSee('This is my club');
    $this->get(route('ranges.show', $venue->slug))->assertOk()->assertDontSee('This is my range');
    $this->get(route('events.show', $event))->assertOk()->assertDontSee('This is my match');
});

it('tells a guest to register before claiming a club', function () {
    $club = Organisation::factory()->create([
        'name' => 'Open Club',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('listings.claim', ['type' => 'club', 'slug' => $club->slug]))
        ->assertOk()
        ->assertSee('Create an account')
        ->assertSee('A site admin approves the claim')
        ->assertSessionHas('url.intended', route('listings.claim', ['type' => 'club', 'slug' => $club->slug]));
});

it('keeps the claim page and registration open while the coming-soon gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $club = Organisation::factory()->create([
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('listings.claim', ['type' => 'club', 'slug' => $club->slug]))
        ->assertOk()
        ->assertSee('Create an account');

    $this->get('/register')->assertOk();
});

it('sends a new account back to the claim page', function () {
    EventFake::fake([Registered::class]);

    $url = route('listings.claim', ['type' => 'club', 'slug' => 'open-club']);
    session()->put('url.intended', $url);

    Livewire::test(Register::class)
        ->set('name', 'Sam Shooter')
        ->set('email', 'sam-claim@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->call('register')
        ->assertRedirect($url);
});

it('records a club claim, tells staff, and does not file a second one', function () {
    Mail::fake();

    $user = User::factory()->create();
    $club = Organisation::factory()->create([
        'name' => 'Claimable Club',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitClaim::class, ['type' => 'club', 'slug' => $club->slug])
        ->set('evidence', 'I am the club secretary and the phone number on the page is ours.')
        ->call('submit')
        ->assertSee('Your claim is in for review');

    $claim = Claim::query()->firstOrFail();

    expect($claim->claimable_type)->toBe('organisation')
        ->and($claim->claimable_id)->toBe($club->id)
        ->and($claim->user_id)->toBe($user->id)
        ->and($claim->status)->toBe(ClaimStatus::Pending);

    Mail::assertQueued(ListingClaimSubmittedMail::class, function (ListingClaimSubmittedMail $mail) use ($club): bool {
        return $mail->hasTo('dirk@example.test')
            && $mail->listingName === $club->name
            && $mail->listingKind === 'club';
    });

    Livewire::actingAs($user)
        ->test(SubmitClaim::class, ['type' => 'club', 'slug' => $club->slug])
        ->call('submit');

    expect(Claim::query()->count())->toBe(1);
});

it('requires a real explanation before filing a claim', function () {
    $user = User::factory()->create();
    $club = Organisation::factory()->create([
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitClaim::class, ['type' => 'club', 'slug' => $club->slug])
        ->set('evidence', 'too short')
        ->call('submit')
        ->assertHasErrors(['evidence' => 'min']);

    expect(Claim::query()->count())->toBe(0);
});

it('files a match claim against the unclaimed host club', function () {
    Mail::fake();

    $user = User::factory()->create();
    $club = Organisation::factory()->create([
        'name' => 'Host Club',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'title' => 'Saturday Gong',
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitClaim::class, ['type' => 'match', 'slug' => $event->slug])
        ->assertSee('Saturday Gong')
        ->set('evidence', 'I run this match for the host club and the contact number is mine.')
        ->call('submit')
        ->assertSee('Your claim is in for review');

    $claim = Claim::query()->firstOrFail();

    expect($claim->claimable_type)->toBe('organisation')
        ->and($claim->claimable_id)->toBe($club->id);

    Mail::assertQueued(ListingClaimSubmittedMail::class, function (ListingClaimSubmittedMail $mail): bool {
        return $mail->listingKind === 'match' && $mail->listingName === 'Host Club';
    });
});

it('records a range claim without making the person a match director', function () {
    Mail::fake();

    $user = User::factory()->create();
    $venue = Venue::factory()->create([
        'name' => 'Claimable Range',
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitClaim::class, ['type' => 'range', 'slug' => $venue->slug])
        ->set('evidence', 'I manage this range and the day-fee number on the page is ours.')
        ->call('submit')
        ->assertSee('Your claim is in for review');

    $claim = Claim::query()->firstOrFail();
    $claim->approve(User::factory()->staff()->create());

    expect($venue->fresh()->claimed_by)->toBe($user->id)
        ->and($user->fresh()->is_match_director)->toBeFalse();
});

it('approves a club claim by recording the owner and opening the desk', function () {
    $user = User::factory()->create(['is_match_director' => false]);
    $club = Organisation::factory()->create([
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);
    $claim = Claim::query()->create([
        'claimable_type' => 'organisation',
        'claimable_id' => $club->id,
        'user_id' => $user->id,
        'evidence' => 'I am the club secretary and the phone number on the page is ours.',
        'status' => ClaimStatus::Pending,
    ]);

    $claim->approve(User::factory()->staff()->create());

    expect($club->fresh()->claimed_by)->toBe($user->id)
        ->and($user->fresh()->is_match_director)->toBeTrue()
        ->and($user->fresh()->md_approved_at)->not->toBeNull();

    $membership = OrganisationUser::query()
        ->where('organisation_id', $club->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role)->toBe(OrganisationUserRole::Admin);
});

it('refuses a claim on a draft match or an unpublished club', function () {
    $user = User::factory()->create();
    $club = Organisation::factory()->create([
        'claimed_by' => null,
        'status' => ListingStatus::Pending,
    ]);
    $event = Event::factory()->create([
        'status' => EventStatus::Draft,
        'host_organisation_id' => Organisation::factory()->create([
            'status' => ListingStatus::Published,
            'claimed_by' => null,
        ])->id,
    ]);

    $this->actingAs($user)
        ->get(route('listings.claim', ['type' => 'club', 'slug' => $club->slug]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('listings.claim', ['type' => 'match', 'slug' => $event->slug]))
        ->assertNotFound();
});
