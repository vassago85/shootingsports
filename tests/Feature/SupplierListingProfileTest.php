<?php

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Livewire\Suppliers\CreateListing;
use App\Livewire\Suppliers\EditListing;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

it('shows the short description, services, and logo on a supplier profile', function () {
    $provider = Provider::factory()->create([
        'slug' => 'tuneup-long-range',
        'name' => 'Tuneup Long Range Precision',
        'category' => ProviderCategory::Instructor,
        'services' => [ProviderCategory::Optics->value, ProviderCategory::Dealer->value],
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'tagline' => 'Long-range coaching and everyday stock.',
        'description' => 'A longer profile of the academy, the range days, and what a new shooter should expect.',
        'logo_path' => 'provider-logos/tuneup.png',
        'website_url' => 'https://tuneup.example',
        'email' => 'secret@tuneup.example',
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.show', $provider->slug))
        ->assertOk()
        ->assertSee('Long-range coaching and everyday stock.')
        ->assertSee('A longer profile of the academy')
        ->assertSee('Services and products')
        ->assertSee('Primary')
        ->assertSee('Secondary')
        ->assertSee('Optics')
        ->assertSee('Dealer')
        ->assertSee('provider-logos/tuneup.png', false)
        ->assertSee('Enquire via platform')
        ->assertDontSee('secret@tuneup.example');
});

it('shows the short description on the category listing', function () {
    $provider = Provider::factory()->create([
        'name' => 'Tuneup Long Range Precision',
        'category' => ProviderCategory::Instructor,
        'town' => 'Pretoria',
        'tagline' => 'Long-range coaching and everyday stock.',
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.category', $provider->category->urlSlug()))
        ->assertOk()
        ->assertSee('Tuneup Long Range Precision')
        ->assertSee('Long-range coaching and everyday stock.');
});

it('lists a supplier under each secondary industry category after the primary listings', function () {
    Provider::factory()->create([
        'name' => 'Optics Only Shop',
        'category' => ProviderCategory::Optics,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    Provider::factory()->create([
        'name' => 'Tuneup Long Range Precision',
        'category' => ProviderCategory::Instructor,
        'services' => [ProviderCategory::Optics->value, ProviderCategory::Dealer->value],
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    Provider::factory()->create([
        'name' => 'Hidden Optics Desk',
        'category' => ProviderCategory::Dealer,
        'services' => [ProviderCategory::Optics->value],
        'status' => ListingStatus::Pending,
    ]);

    Provider::factory()->create([
        'name' => 'Zulu Range Optics',
        'category' => ProviderCategory::Instructor,
        'services' => [ProviderCategory::Optics->value],
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.category', 'optics'))
        ->assertOk()
        ->assertSeeInOrder([
            'Optics Only Shop',
            'Secondary',
            'Tuneup Long Range Precision',
            'Primary · Instructor / academy',
        ])
        ->assertDontSee('Hidden Optics Desk');

    $this->get(route('suppliers.province', ['optics', 'gauteng']))
        ->assertOk()
        ->assertSee('Tuneup Long Range Precision')
        ->assertDontSee('Zulu Range Optics');

    $this->get(route('suppliers.category', 'dealer'))
        ->assertOk()
        ->assertSee('Tuneup Long Range Precision')
        ->assertSee('Primary · Instructor / academy');

    $this->get(route('suppliers.category', 'instructor'))
        ->assertOk()
        ->assertSee('Tuneup Long Range Precision')
        ->assertDontSee('Optics Only Shop');

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee(route('suppliers.category', 'optics'), false);
});

it('stores an optional logo and short description when a supplier registers', function () {
    Storage::fake('media');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateListing::class)
        ->set('name', 'Tuneup Long Range Precision')
        ->set('category', ProviderCategory::Instructor->value)
        ->set('services', [ProviderCategory::Optics->value])
        ->set('province', Province::Gauteng->value)
        ->set('town', 'Pretoria')
        ->set('tagline', 'Long-range coaching and everyday stock.')
        ->set('description', 'A longer profile of the academy and what a new shooter should expect.')
        ->set('logo', UploadedFile::fake()->image('logo.png', 400, 400))
        ->call('submit')
        ->assertRedirect();

    $provider = Provider::query()->where('claimed_by', $user->id)->firstOrFail();

    expect($provider->tagline)->toBe('Long-range coaching and everyday stock.')
        ->and($provider->logo_path)->toStartWith('provider-logos/')
        ->and($provider->source)->toBe(ListingSource::Claimed);

    Storage::disk('media')->assertExists($provider->logo_path);
});

it('rejects a non-image logo and a short description over 160 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateListing::class)
        ->set('name', 'Tuneup Long Range Precision')
        ->set('category', ProviderCategory::Instructor->value)
        ->set('province', Province::Gauteng->value)
        ->set('town', 'Pretoria')
        ->set('description', 'A longer profile of the academy and what a new shooter should expect.')
        ->set('tagline', str_repeat('a', 161))
        ->set('logo', UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors(['tagline', 'logo']);
});

it('lets the owner update the logo and short description', function () {
    Storage::fake('media');
    $owner = User::factory()->create();
    $provider = Provider::factory()->create([
        'claimed_by' => $owner->id,
        'status' => ListingStatus::Pending,
        'description' => 'A longer profile of the academy and what a new shooter should expect.',
        'tagline' => null,
        'logo_path' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(EditListing::class, ['provider' => $provider])
        ->set('tagline', 'Long-range coaching and everyday stock.')
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertRedirect(route('suppliers.onboard.thanks', ['provider' => $provider->slug]));

    $provider->refresh();

    expect($provider->tagline)->toBe('Long-range coaching and everyday stock.')
        ->and($provider->logo_path)->toStartWith('provider-logos/');

    Storage::disk('media')->assertExists($provider->logo_path);
});

it('lets the owner update contact details and extra services without changing the public address', function () {
    $owner = User::factory()->create();
    $provider = Provider::factory()->create([
        'slug' => 'tuneup-long-range',
        'name' => 'Tuneup Long Range',
        'claimed_by' => $owner->id,
        'category' => ProviderCategory::Instructor,
        'status' => ListingStatus::Published,
        'description' => 'A longer profile of the academy and what a new shooter should expect.',
        'email' => null,
        'phone' => null,
        'website_url' => null,
        'services' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(EditListing::class, ['provider' => $provider])
        ->set('name', 'Tuneup Precision')
        ->set('phone', '011 555 0101')
        ->set('email', 'shop@tuneup.example')
        ->set('website_url', 'https://tuneup.example')
        ->set('services', [ProviderCategory::Optics->value, ProviderCategory::Instructor->value])
        ->call('save')
        ->assertRedirect(route('suppliers.onboard.thanks', ['provider' => 'tuneup-long-range']));

    $provider->refresh();

    expect($provider->slug)->toBe('tuneup-long-range')
        ->and($provider->name)->toBe('Tuneup Precision')
        ->and($provider->phone)->toBe('011 555 0101')
        ->and($provider->email)->toBe('shop@tuneup.example')
        ->and($provider->website_url)->toBe('https://tuneup.example')
        ->and($provider->services)->toBe([ProviderCategory::Optics->value])
        ->and($provider->status)->toBe(ListingStatus::Published);
});

it('refuses another user from editing a supplier listing', function () {
    $owner = User::factory()->create();
    $provider = Provider::factory()->create([
        'claimed_by' => $owner->id,
        'description' => 'A longer profile of the academy and what a new shooter should expect.',
    ]);

    $this->get(route('suppliers.onboard.edit', $provider))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('suppliers.onboard.edit', $provider))
        ->assertForbidden();
});
