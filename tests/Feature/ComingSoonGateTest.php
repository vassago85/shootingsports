<?php

use App\Http\Middleware\EnsureComingSoonAccess;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Coverage for the coming-soon site gate (config('coming-soon.enabled')).
 *
 * Two states matter: gate OFF (baseline — everything works as before)
 * and gate ON (public routes redirect to /coming-soon unless the path
 * is allowlisted or the user is staff / a match director).
 */

beforeEach(function (): void {
    $this->withoutVite();
});

// ---- Gate OFF: baseline is untouched --------------------------------

it('lets guests reach the home page when the gate is off', function () {
    config()->set('coming-soon.enabled', false);

    $this->get('/')->assertOk();
});

it('lets guests reach the register page when the gate is off', function () {
    config()->set('coming-soon.enabled', false);

    $this->get('/register')->assertOk();
});

// ---- Gate ON: guests and shooters see coming soon -------------------

it('redirects guests to /coming-soon when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/')->assertRedirect(route('coming-soon'));
    $this->get('/calendar')->assertRedirect(route('coming-soon'));
});

it('redirects the register routes to /coming-soon when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/register')->assertRedirect(route('coming-soon'));
    $this->get('/directors/register')->assertRedirect(route('coming-soon'));
});

it('redirects authenticated shooters to /coming-soon when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('coming-soon'));
});

// ---- Gate ON: staff and match directors bypass ----------------------

it('lets staff through to the real site when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->actingAs(User::factory()->staff()->create())
        ->get('/')
        ->assertOk();
});

it('lets match directors through to the real site when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->actingAs(User::factory()->matchDirector()->create())
        ->get('/')
        ->assertOk();
});

// ---- Gate ON: allowlisted paths always reachable --------------------

it('keeps /login reachable when the gate is on so builders can sign in', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/login')->assertOk();
});

it('lets the hashed livewire update endpoint through the gate', function () {
    config()->set('coming-soon.enabled', true);

    $path = parse_url(route('default-livewire.update'), PHP_URL_PATH);

    $response = app(EnsureComingSoonAccess::class)->handle(
        Request::create($path, 'POST'),
        fn () => response('passed'),
    );

    expect($response->getContent())->toBe('passed');
});

it('serves sitemap xml while the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', false);

    $this->get('/sitemaps/events.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset', false);
});

it('keeps the health check reachable when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/up')->assertOk();
});

it('renders the coming-soon page for guests', function () {
    config()->set('coming-soon.enabled', true);

    $this->get('/coming-soon')
        ->assertOk()
        ->assertSee('national register of')
        ->assertSee('Log in');
});

// ---- Post-login redirect while gated --------------------------------

it('sends a shooter to /coming-soon after login when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

    User::factory()->create([
        'email' => 'sam@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->call('authenticate')
        ->assertRedirect(route('coming-soon'));
});

it('still sends staff to /admin after login when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

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

it('still sends match directors to /desk after login when the gate is on', function () {
    config()->set('coming-soon.enabled', true);

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
