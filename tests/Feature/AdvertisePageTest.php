<?php

use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Models\Enquiry;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
});

it('renders every advertising product on the rate card', function () {
    // Bundle A: Enhanced supplier listing is gated until Industry has
    // real listings — seed past the threshold so the full rate card
    // still renders for this assertion.
    Provider::factory()->count(5)->create([
        'status' => ListingStatus::Published,
    ]);
    Cache::flush();

    $response = $this->get(route('advertise'))->assertOk();

    foreach (config('advertising.products') as $product) {
        $response->assertSee($product['name'])
            ->assertDontSee($product['price_display']);
    }
});

it('hides the Enhanced supplier listing pitch when Industry is empty', function () {
    Provider::query()->delete();
    Cache::flush();

    $html = $this->get(route('advertise'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('Enhanced supplier listing')
        ->toContain('Industry directory is still filling up')
        ->toContain(route('claim'));
});

it('renders the public commitments block', function () {
    $response = $this->get(route('advertise'))->assertOk();

    foreach (config('advertising.commitments') as $line) {
        $response->assertSee(e($line));
    }
});

it('does not publish advertising prices', function () {
    $html = $this->get(route('advertise'))->assertOk()->getContent();

    expect($html)->toContain('"@type":"Service"')
        ->and($html)->not->toContain('"@type":"Offer"')
        ->and($html)->not->toContain('"priceCurrency"')
        ->and($html)->not->toContain('Rate card');

    foreach (config('advertising.products') as $product) {
        expect($html)->not->toContain($product['price_display']);
    }
});

it('persists the selected product key into enquiries.context', function () {
    $response = $this->post(route('enquiries.store'), [
        'type' => 'advertise',
        'product' => 'discover_placement',
        'name' => 'Jane Advertiser',
        'email' => 'jane@example.com',
        'body' => 'We would like to sponsor the precision rifle page.',
        'form_loaded_at' => time() - 10,
    ]);

    $response->assertRedirect(route('enquiries.thanks'));

    $enquiry = Enquiry::query()->latest('id')->first();
    expect($enquiry)->not->toBeNull()
        ->and($enquiry->type)->toBe(EnquiryType::Advertise)
        ->and($enquiry->context)->toBe(['product' => 'discover_placement']);
});

it('rejects an advertise enquiry with an unknown product key', function () {
    $response = $this->post(route('enquiries.store'), [
        'type' => 'advertise',
        'product' => 'not_a_real_product',
        'name' => 'Bad Bot',
        'email' => 'bot@example.com',
        'body' => 'Attempting to smuggle an unknown product key.',
        'form_loaded_at' => time() - 10,
    ]);

    $response->assertSessionHasErrors('product');
    expect(Enquiry::query()->count())->toBe(0);
});

it('allows an advertise enquiry with no product (unsure sender)', function () {
    $response = $this->post(route('enquiries.store'), [
        'type' => 'advertise',
        'name' => 'Curious Advertiser',
        'email' => 'curious@example.com',
        'body' => 'Not sure yet, send me the options please.',
        'form_loaded_at' => time() - 10,
    ]);

    $response->assertRedirect(route('enquiries.thanks'));

    $enquiry = Enquiry::query()->latest('id')->first();
    expect($enquiry->context)->toBeNull();
});

it('records the authed user id on any enquiry submission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('enquiries.store'), [
        'type' => 'general',
        'name' => $user->name,
        'email' => $user->email,
        'body' => 'This is my message from a signed in shooter.',
        'form_loaded_at' => time() - 10,
    ])->assertRedirect(route('enquiries.thanks'));

    $enquiry = Enquiry::query()->latest('id')->first();
    expect($enquiry->user_id)->toBe($user->id);
});
