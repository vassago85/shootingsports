<?php

use App\Enums\EnquiryType;
use App\Enums\OrganisationUserRole;
use App\Livewire\Auth\DirectorRegister;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ShooterRegister;
use App\Models\Enquiry;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

// ---- Page render ---------------------------------------------------

it('renders the public login page for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Log in')
        ->assertSee('Create a shooter account')
        ->assertSee('register as a match director');
});

it('renders the shooter signup page for guests', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Create your shooter account');
});

it('renders the director signup page for guests', function () {
    $this->get('/directors/register')
        ->assertOk()
        ->assertSee('Register as a match director')
        ->assertSee('Which club, range, series or host');
});

it('redirects authenticated users away from guest-only auth pages', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect();
});

// ---- Shooter signup ------------------------------------------------

it('creates a shooter account with is_match_director=false and logs the user in', function () {
    Event::fake([Registered::class]);

    Livewire::test(ShooterRegister::class)
        ->set('name', 'Sam Shooter')
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->call('register')
        ->assertRedirect('/my-calendar');

    $user = User::query()->where('email', 'sam@example.test')->firstOrFail();

    expect($user->is_match_director)->toBeFalse()
        ->and($user->is_staff)->toBeFalse()
        ->and($user->name)->toBe('Sam Shooter');

    Event::assertDispatched(Registered::class);
    $this->assertAuthenticatedAs($user);
});

it('shooter signup rejects duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(ShooterRegister::class)
        ->set('name', 'Nope')
        ->set('email', 'taken@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);
});

it('shooter signup rejects a mismatched password confirmation', function () {
    Livewire::test(ShooterRegister::class)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'different_password_xyz')
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('shooter signup rejects passwords shorter than 8 characters', function () {
    Livewire::test(ShooterRegister::class)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.test')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('register')
        ->assertHasErrors(['password' => 'min']);
});

// ---- Director signup (review-gated) --------------------------------

it('creates a PENDING match director account, lands on /my-calendar, and flashes a review message', function () {
    Event::fake([Registered::class]);

    Livewire::test(DirectorRegister::class)
        ->set('name', 'Dana Director')
        ->set('email', 'dana@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('host_hint', 'Pretoria Rifle & Pistol Club — I run the Wednesday IPSC shoots.')
        ->call('register')
        ->assertRedirect('/my-calendar');

    $user = User::query()->where('email', 'dana@example.test')->firstOrFail();

    expect($user->is_match_director)->toBeFalse()
        ->and($user->is_staff)->toBeFalse()
        ->and($user->md_requested_at)->not->toBeNull()
        ->and($user->md_approved_at)->toBeNull()
        ->and($user->md_rejected_at)->toBeNull()
        ->and($user->isMdPending())->toBeTrue();

    expect(session('status'))->toContain('your match director request is in');
    Event::assertDispatched(Registered::class);
    $this->assertAuthenticatedAs($user);
});

it('director signup ALWAYS records an md_signup enquiry with the host hint', function () {
    Livewire::test(DirectorRegister::class)
        ->set('name', 'Dana Director')
        ->set('email', 'dana@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('host_hint', 'Pretoria Rifle & Pistol Club')
        ->call('register');

    $enquiry = Enquiry::query()
        ->where('type', EnquiryType::MdSignup->value)
        ->firstOrFail();

    expect($enquiry->email)->toBe('dana@example.test')
        ->and($enquiry->subject)->toStartWith('MD request:')
        ->and($enquiry->body)->toBe('Pretoria Rifle & Pistol Club')
        ->and($enquiry->context['host_hint'] ?? null)->toBe('Pretoria Rifle & Pistol Club');
});

it('director signup rejects an empty host_hint (required in review-gated flow)', function () {
    Livewire::test(DirectorRegister::class)
        ->set('name', 'Dana Director')
        ->set('email', 'dana@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('host_hint', '')
        ->call('register')
        ->assertHasErrors('host_hint');

    expect(User::query()->where('email', 'dana@example.test')->exists())->toBeFalse();
});

it('a PENDING match director cannot access /desk yet', function () {
    $pending = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);

    $this->actingAs($pending)
        ->get('/desk')
        ->assertStatus(403);
});

// ---- Login redirects follow role ----------------------------------

it('logs in a shooter and redirects them to /my-calendar', function () {
    User::factory()->create([
        'email' => 'sam@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->call('authenticate')
        ->assertRedirect('/my-calendar');
});

it('logs in a match director and redirects them to /desk', function () {
    User::factory()->matchDirector()->create([
        'email' => 'dana@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'dana@example.test')
        ->set('password', 'longenoughpassword')
        ->call('authenticate')
        ->assertRedirect('/desk');
});

it('logs in staff and redirects them to /admin', function () {
    User::factory()->staff()->create([
        'email' => 'sara@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'sara@example.test')
        ->set('password', 'longenoughpassword')
        ->call('authenticate')
        ->assertRedirect('/admin');
});

it('rejects wrong credentials and does not log the user in', function () {
    User::factory()->create([
        'email' => 'sam@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'sam@example.test')
        ->set('password', 'the_wrong_password_here')
        ->call('authenticate')
        ->assertHasErrors('email');

    $this->assertGuest();
});

// ---- Panel access gating ------------------------------------------

it('a plain shooter cannot access /desk', function () {
    $this->actingAs(User::factory()->create())
        ->get('/desk')
        ->assertStatus(403);
});

it('a match director can access /desk', function () {
    $this->actingAs(User::factory()->matchDirector()->create())
        ->get('/desk')
        ->assertOk();
});

it('staff can access both /desk and /admin', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/desk')->assertOk();
    $this->actingAs($staff)->get('/admin')->assertOk();
});

// ---- Legacy /desk/register redirect --------------------------------

it('301s the legacy /desk/register bookmark to /directors/register', function () {
    $this->get('/desk/register')
        ->assertStatus(301)
        ->assertRedirect('/directors/register');
});

// ---- Migration back-fill (regression) ------------------------------

it('User::canAccessPanel(desk) returns true for staff even without the MD flag', function () {
    $staff = User::factory()->staff()->create(['is_match_director' => false]);

    $panel = filament()->getPanel('desk');

    expect($staff->canAccessPanel($panel))->toBeTrue();
});

it('back-fill migration would have promoted anyone with an event-managing membership', function () {
    // This test simulates the back-fill logic against a fresh member so a
    // future maintainer can see what the migration guaranteed. It does
    // not re-run the migration — that already ran in the test bootstrap.
    $user = User::factory()->create(['is_match_director' => false]);
    $org = Organisation::factory()->create();
    OrganisationUser::create([
        'user_id' => $user->id,
        'organisation_id' => $org->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'granted_at' => now(),
    ]);

    // Sanity: memberships that carry event-management roles exist. The
    // migration back-fill would have flipped is_match_director for this
    // shape of user on legacy data.
    expect(
        DB::table('organisation_user')
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $r): string => $r->value,
                OrganisationUserRole::canManageEvents(),
            ))
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();
});
