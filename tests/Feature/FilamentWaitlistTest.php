<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ProviderTier;
use App\Filament\Pages\StaffDashboard;
use App\Models\Enquiry;
use App\Models\User;
use App\Support\ProviderTierPricing;

beforeEach(function () {
    $this->withoutVite();
});

it('groups waitlist enquiries by trigger for the current month', function () {
    $user = User::factory()->create(['is_staff' => true]);

    // Two follow_limit + one saved_search_limit this month
    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $user->id,
        'name' => 'A',
        'email' => 'a@example.com',
        'body' => 'x',
        'context' => ['trigger' => 'follow_limit'],
        'status' => EnquiryStatus::New,
        'created_at' => now(),
    ]);

    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $user->id,
        'name' => 'B',
        'email' => 'b@example.com',
        'body' => 'x',
        'context' => ['trigger' => 'follow_limit'],
        'status' => EnquiryStatus::New,
        'created_at' => now(),
    ]);

    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $user->id,
        'name' => 'C',
        'email' => 'c@example.com',
        'body' => 'x',
        'context' => ['trigger' => 'saved_search_limit'],
        'status' => EnquiryStatus::New,
        'created_at' => now(),
    ]);

    // One last month — should be excluded. Eloquent overwrites the
    // timestamps on create(), so we backdate via a second update().
    $oldRow = Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $user->id,
        'name' => 'D',
        'email' => 'd@example.com',
        'body' => 'x',
        'context' => ['trigger' => 'history_window'],
        'status' => EnquiryStatus::New,
    ]);
    $oldRow->timestamps = false;
    $oldRow->forceFill(['created_at' => now()->subMonth()->startOfMonth()])->save();

    // Non-waitlist enquiry — should be excluded
    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'E',
        'email' => 'e@example.com',
        'body' => 'x',
        'status' => EnquiryStatus::New,
    ]);

    $reflection = new ReflectionClass(StaffDashboard::class);
    $method = $reflection->getMethod('waitlistThisMonth');
    $method->setAccessible(true);

    $dashboard = new StaffDashboard;
    $result = $method->invoke($dashboard);

    expect($result)->toBe([
        'follow_limit' => 2,
        'saved_search_limit' => 1,
    ]);
});

it('provider tier helper reads featured price from config/advertising.php', function () {
    $expected = config('advertising.products.featured.price_display');

    expect(ProviderTierPricing::helperFor(ProviderTier::Featured))
        ->toContain($expected);
});

it('provider tier helper describes free and verified tiers', function () {
    expect(ProviderTierPricing::helperFor(ProviderTier::Free))
        ->toContain('free listing')
        ->and(ProviderTierPricing::helperFor(ProviderTier::Verified))
        ->toContain('Staff-confirmed');
});

it('provider tier helper returns null for an unknown tier', function () {
    expect(ProviderTierPricing::helperFor('bogus'))->toBeNull();
});
