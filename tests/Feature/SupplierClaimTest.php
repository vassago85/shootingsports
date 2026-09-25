<?php

use App\Enums\ClaimStatus;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Livewire\Suppliers\ClaimListing;
use App\Mail\SupplierClaimSubmittedMail;
use App\Models\Claim;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    config()->set('registration.notify_emails', ['dirk@example.test']);
});

it('shows a claim link on an unclaimed supplier page', function () {
    $provider = Provider::factory()->create([
        'name' => 'Unclaimed Guns',
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.show', $provider))
        ->assertOk()
        ->assertSee('This is my business')
        ->assertSee(route('suppliers.claim', $provider), false);
});

it('hides the claim link once a supplier page has an owner', function () {
    $provider = Provider::factory()->create([
        'name' => 'Owned Guns',
        'category' => ProviderCategory::Dealer,
        'claimed_by' => User::factory()->create()->id,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.show', $provider))
        ->assertOk()
        ->assertDontSee('This is my business');
});

it('sends a guest who wants to claim a listing to log in', function () {
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.claim', $provider))
        ->assertRedirect(route('login'));
});

it('refuses a claim on a listing that is not public yet', function () {
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Pending,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('suppliers.claim', $provider))
        ->assertNotFound();
});

it('records a claim, tells staff, and does not file a second one', function () {
    Mail::fake();

    $user = User::factory()->create();
    $provider = Provider::factory()->create([
        'name' => 'Unclaimed Guns',
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($user)
        ->test(ClaimListing::class, ['provider' => $provider])
        ->set('evidence', 'I own this shop and the phone number on the page is ours.')
        ->call('submit')
        ->assertSee('Your claim is in for review');

    $claim = Claim::query()->firstOrFail();

    expect($claim->claimable_type)->toBe('provider')
        ->and($claim->claimable_id)->toBe($provider->id)
        ->and($claim->user_id)->toBe($user->id)
        ->and($claim->status)->toBe(ClaimStatus::Pending);

    Mail::assertQueued(SupplierClaimSubmittedMail::class, function (SupplierClaimSubmittedMail $mail): bool {
        return $mail->hasTo('dirk@example.test')
            && $mail->provider->name === 'Unclaimed Guns';
    });

    Livewire::actingAs($user)
        ->test(ClaimListing::class, ['provider' => $provider])
        ->call('submit');

    expect(Claim::query()->count())->toBe(1);
});

it('requires a real explanation before filing a claim', function () {
    $user = User::factory()->create();
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($user)
        ->test(ClaimListing::class, ['provider' => $provider])
        ->set('evidence', 'too short')
        ->call('submit')
        ->assertHasErrors('evidence');

    expect(Claim::query()->count())->toBe(0);
});

it('does not let someone who already has a listing claim another', function () {
    $user = User::factory()->create();
    Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => $user->id,
    ]);
    $listed = Provider::factory()->create([
        'name' => 'Someone Elses Shop',
        'category' => ProviderCategory::Optics,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->actingAs($user)
        ->get(route('suppliers.claim', $listed))
        ->assertOk()
        ->assertSee('already has a business listing');

    expect(Claim::query()->count())->toBe(0);
});

it('does not offer a claim form when the listing already has an owner', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => $owner->id,
        'status' => ListingStatus::Published,
    ]);

    $this->actingAs($visitor)
        ->get(route('suppliers.claim', $provider))
        ->assertOk()
        ->assertSee('already has an owner');

    expect(Claim::query()->count())->toBe(0);
});

it('gives the listing to the claimant when staff approve the claim', function () {
    $user = User::factory()->create();
    $provider = Provider::factory()->create([
        'category' => ProviderCategory::Dealer,
        'claimed_by' => null,
        'status' => ListingStatus::Published,
    ]);
    $claim = Claim::query()->create([
        'claimable_type' => 'provider',
        'claimable_id' => $provider->id,
        'user_id' => $user->id,
        'evidence' => 'I own this shop and the phone number on the page is ours.',
        'status' => ClaimStatus::Pending,
    ]);

    $claim->approve(User::factory()->staff()->create());

    expect($provider->fresh()->claimed_by)->toBe($user->id)
        ->and($claim->fresh()->status)->toBe(ClaimStatus::Approved);
});
