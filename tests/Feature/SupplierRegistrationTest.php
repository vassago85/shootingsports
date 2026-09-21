<?php

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Livewire\Auth\Register;
use App\Livewire\Suppliers\CreateListing;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    // Every supplier flow test runs with the gate off — the gate
    // itself is exercised in ComingSoonGateTest.
    config()->set('coming-soon.enabled', false);
});

// ---- Page render ---------------------------------------------------

it('renders the unified signup page with a supplier tickbox for guests', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Supplier / industry business')
        ->assertSee('Match director, club or series admin');
});

it('301s the old /suppliers/register bookmark to /register', function () {
    $this->get('/suppliers/register')
        ->assertStatus(301)
        ->assertRedirect('/register');
});

// ---- Signup with the supplier role ticked --------------------------

it('ticking supplier creates an unverified account, dispatches a verification notification, and sends them to /email/verify', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->set('name', 'Sue Supplier')
        ->set('email', 'sue@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('wants_supplier', true)
        ->set('business_name', 'Delmas Gun Shop')
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'sue@example.test')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->is_staff)->toBeFalse()
        ->and($user->is_match_director)->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertAuthenticatedAs($user);

    // Pending business name is stashed in the session so the listing
    // form step 2 can pre-fill it without the applicant retyping.
    expect(session('supplier.pending_business_name'))->toBe('Delmas Gun Shop');
});

it('ticking supplier requires a business_name', function () {
    Livewire::test(Register::class)
        ->set('name', 'Sue Supplier')
        ->set('email', 'sue@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('wants_supplier', true)
        ->set('business_name', '')
        ->call('register')
        ->assertHasErrors('business_name');
});

it('does not require business_name when the supplier box is not ticked', function () {
    Livewire::test(Register::class)
        ->set('name', 'Sam Shooter')
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('wants_supplier', false)
        ->set('business_name', '')
        ->call('register')
        ->assertHasNoErrors();
});

// ---- Email verification gate --------------------------------------

it('blocks unverified users from the supplier onboarding form', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/suppliers/onboard')
        ->assertRedirect(route('verification.notice'));
});

it('lets verified users through to the supplier onboarding form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/suppliers/onboard')
        ->assertOk()
        ->assertSee('Register your services');
});

it('the signed verification link marks the user verified and redirects them onwards', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

// ---- Listing creation ---------------------------------------------

it('creates a pending provider listing owned by the user with services stored as an array', function () {
    $user = User::factory()->create();
    session(['supplier.pending_business_name' => 'Delmas Gun Shop']);

    Livewire::actingAs($user)
        ->test(CreateListing::class)
        ->assertSet('name', 'Delmas Gun Shop')
        ->set('category', ProviderCategory::Dealer->value)
        ->set('services', [
            ProviderCategory::Optics->value,
            ProviderCategory::Ammunition->value,
            ProviderCategory::Dealer->value, // dupe of primary, must be stripped
        ])
        ->set('province', Province::Gauteng->value)
        ->set('town', 'Delmas')
        ->set('email', 'shop@example.test')
        ->set('phone', '011 555 1234')
        ->set('website_url', 'https://example.test')
        ->set('description', 'A long-enough description of a small-town gun shop with everyday stock.')
        ->call('submit')
        ->assertRedirect();

    $provider = Provider::query()->where('claimed_by', $user->id)->firstOrFail();

    expect($provider->name)->toBe('Delmas Gun Shop')
        ->and($provider->category)->toBe(ProviderCategory::Dealer)
        ->and($provider->status)->toBe(ListingStatus::Pending)
        ->and($provider->verification_state)->toBe(VerificationState::Unconfirmed)
        ->and($provider->source)->toBe(ListingSource::Claimed)
        ->and($provider->claimed_by)->toBe($user->id)
        ->and($provider->services)->toEqualCanonicalizing([
            ProviderCategory::Optics->value,
            ProviderCategory::Ammunition->value,
        ])
        ->and($provider->serviceCategories()->count())->toBe(2);
});

it('requires primary category, province, town and a 20-character description', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateListing::class)
        ->set('name', 'Missing Data Shop')
        ->set('category', '')
        ->set('province', '')
        ->set('town', '')
        ->set('description', 'too short')
        ->call('submit')
        ->assertHasErrors(['category', 'province', 'town', 'description']);
});

it('redirects a supplier who already has a listing away from the create form to their thanks page', function () {
    $user = User::factory()->create();
    $existing = Provider::factory()->create([
        'claimed_by' => $user->id,
        'status' => ListingStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get('/suppliers/onboard')
        ->assertRedirect(route('suppliers.onboard.thanks', ['provider' => $existing->slug]));
});

// ---- Thanks page --------------------------------------------------

it('shows the thanks page to the owner and 403s everyone else', function () {
    $owner = User::factory()->create();
    $provider = Provider::factory()->create([
        'claimed_by' => $owner->id,
        'status' => ListingStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->get(route('suppliers.onboard.thanks', ['provider' => $provider->slug]))
        ->assertOk()
        ->assertSee($provider->name);

    $someoneElse = User::factory()->create();
    $this->actingAs($someoneElse)
        ->get(route('suppliers.onboard.thanks', ['provider' => $provider->slug]))
        ->assertStatus(403);
});

// ---- Nav visibility ------------------------------------------------

it('does not show the My business nav entry to guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('My business');
});

it('does not show the My business nav entry to a signed-in user without a listing', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertDontSee('My business');
});

it('shows the My business nav entry to a user who owns a supplier listing', function () {
    $user = User::factory()->create();
    Provider::factory()->create(['claimed_by' => $user->id]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('My business')
        ->assertSee(route('suppliers.onboard'), false);
});

// ---- Public serviceCategories helper ------------------------------

it('provider serviceCategories() drops the primary and unknown values', function () {
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'services' => [
            ProviderCategory::Optics->value,
            ProviderCategory::Dealer->value, // primary — should be dropped
            'not_a_real_category', // unknown — should be dropped
            ProviderCategory::Ammunition->value,
        ],
    ]);

    $labels = $provider->serviceCategories()->map(fn ($c) => $c->getLabel())->all();

    expect($labels)->toEqualCanonicalizing(['Optics', 'Ammunition']);
});
