<?php

use App\Actions\ApproveSubmittedMatch;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\Province;
use App\Livewire\Matches\SubmitMatch;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Queries\PublicEventQuery;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

it('sends a guest to log in before they can submit a match', function () {
    $this->get(route('matches.submit'))
        ->assertRedirect(route('login'));
});

it('sends an unverified user to confirm their email before they can submit a match', function () {
    $user = User::factory()->unverified()->create([
        'md_requested_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('matches.submit'))
        ->assertRedirect(route('verification.notice'));
});

it('opens the match form for a signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('matches.submit'))
        ->assertOk()
        ->assertSee('List a match');
});

it('stores a draft match off the public calendar until staff approve it', function () {
    $user = User::factory()->create([
        'md_requested_at' => now(),
    ]);
    Enquiry::create([
        'type' => EnquiryType::MdSignup,
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'subject' => 'MD request: '.$user->name,
        'body' => 'Pretoria Rifle Club',
        'status' => EnquiryStatus::New,
    ]);
    $discipline = Discipline::factory()->create(['name' => 'IPSC Handgun']);

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', 'Wednesday IPSC')
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('host_name', 'Pretoria Rifle Club')
        ->set('province', Province::Gauteng->value)
        ->set('town', 'Pretoria')
        ->set('discipline_id', (string) $discipline->id)
        ->set('description', 'Bring eye protection.')
        ->call('submit')
        ->assertRedirect();

    $event = Event::query()->where('title', 'Wednesday IPSC')->firstOrFail();
    $host = $event->hostOrganisation;

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->source->value)->toBe('submission')
        ->and($event->created_by)->toBe($user->id)
        ->and($host->status)->toBe(ListingStatus::Pending)
        ->and($host->claimed_by)->toBe($user->id)
        ->and($user->organisations()->whereKey($host->id)->exists())->toBeTrue()
        ->and($event->disciplines()->whereKey($discipline->id)->exists())->toBeTrue()
        ->and(Enquiry::query()->where('user_id', $user->id)->where('type', EnquiryType::MdSignup)->count())->toBe(1)
        ->and((new PublicEventQuery)->builder()->whereKey($event->id)->exists())->toBeFalse();

    $this->get(route('matches.show', $event->slug))->assertNotFound();

    $this->actingAs($user)
        ->get(route('matches.submit.thanks', ['event' => $event->slug]))
        ->assertOk()
        ->assertSee('Wednesday IPSC')
        ->assertSee('Waiting for approval');

    app(ApproveSubmittedMatch::class)($event);

    $event->refresh();
    $host->refresh();

    expect($event->status)->toBe(EventStatus::Confirmed)
        ->and($host->status)->toBe(ListingStatus::Published)
        ->and((new PublicEventQuery)->builder()->whereKey($event->id)->exists())->toBeTrue();

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee('Wednesday IPSC');
});

it('files a match director request when a shooter submits their first match', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', 'Club steel')
        ->set('starts_on', now()->addDays(3)->toDateString())
        ->set('host_name', 'New Steel Club')
        ->set('province', Province::Gauteng->value)
        ->call('submit')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->isMdPending())->toBeTrue()
        ->and($user->is_match_director)->toBeFalse()
        ->and(Enquiry::query()->where('user_id', $user->id)->where('type', EnquiryType::MdSignup)->where('status', EnquiryStatus::New)->exists())->toBeTrue();
});

it('refuses a match for a club the user does not belong to', function () {
    $user = User::factory()->create();
    Organisation::factory()->create(['name' => 'Pretoria Rifle Club']);

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', 'Wednesday IPSC')
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('host_name', 'Pretoria Rifle Club')
        ->set('province', Province::Gauteng->value)
        ->call('submit')
        ->assertHasErrors('host_name');

    expect(Event::query()->where('title', 'Wednesday IPSC')->exists())->toBeFalse()
        ->and($user->organisations()->count())->toBe(0);
});

it('reuses a club the user already runs', function () {
    $user = User::factory()->create(['is_match_director' => true]);
    $club = Organisation::factory()->create([
        'name' => 'Owned Club',
        'status' => ListingStatus::Published,
    ]);
    $club->users()->attach($user->id, [
        'role' => OrganisationUserRole::MatchDirector->value,
        'granted_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', 'Monthly gong')
        ->set('starts_on', now()->addWeek()->toDateString())
        ->set('host_name', 'Owned Club')
        ->set('province', Province::Gauteng->value)
        ->call('submit')
        ->assertHasNoErrors();

    expect(Organisation::query()->count())->toBe(1);

    $event = Event::query()->where('title', 'Monthly gong')->firstOrFail();

    app(ApproveSubmittedMatch::class)($event);

    expect($event->fresh()->status)->toBe(EventStatus::Confirmed)
        ->and($club->fresh()->status)->toBe(ListingStatus::Published);
});

it('requires a title, date, club and province', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', '')
        ->set('starts_on', '')
        ->set('host_name', '')
        ->set('province', '')
        ->call('submit')
        ->assertHasErrors(['title', 'starts_on', 'host_name', 'province']);
});

it('rejects a match date in the past', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SubmitMatch::class)
        ->set('title', 'Yesterday shoot')
        ->set('starts_on', now()->subDay()->toDateString())
        ->set('host_name', 'New Club')
        ->set('province', Province::Gauteng->value)
        ->call('submit')
        ->assertHasErrors('starts_on');
});

it('hides another persons submitted match', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->draft()->create([
        'created_by' => $owner->id,
        'source' => ListingSource::Submission,
    ]);

    $this->actingAs($stranger)
        ->get(route('matches.submit.thanks', ['event' => $event->slug]))
        ->assertForbidden();
});

it('sends a pending director to the match form after they confirm their email', function () {
    $user = User::factory()->unverified()->create([
        'md_requested_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('matches.submit').'?verified=1');
});

it('sends a shooter to their calendar after they confirm their email', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect('/my-calendar?verified=1');
});

it('opens signup with the match director box ticked from List an event', function () {
    $this->get(route('register', ['director' => 1]))
        ->assertOk()
        ->assertSee('Which club, range, series or host will you publish for?');
});
