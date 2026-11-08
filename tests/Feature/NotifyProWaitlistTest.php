<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Mail\ProSalesOpen;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('emails each waitlist address once and skips people who turned product updates off', function () {
    Mail::fake();

    $wantsIt = User::factory()->create([
        'email' => 'ready@example.com',
        'emails_product_updates_enabled' => true,
    ]);
    $optedOut = User::factory()->create([
        'email' => 'quiet@example.com',
        'emails_product_updates_enabled' => false,
    ]);

    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $wantsIt->id,
        'name' => 'Ready',
        'email' => 'ready@example.com',
        'body' => 'Tell me',
        'context' => ['trigger' => 'follow_limit'],
        'status' => EnquiryStatus::New,
    ]);
    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $wantsIt->id,
        'name' => 'Ready',
        'email' => 'Ready@example.com',
        'body' => 'Second trigger',
        'context' => ['trigger' => 'saved_search_limit'],
        'status' => EnquiryStatus::New,
    ]);
    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'user_id' => $optedOut->id,
        'name' => 'Quiet',
        'email' => 'quiet@example.com',
        'body' => 'Tell me',
        'context' => ['trigger' => 'follow_limit'],
        'status' => EnquiryStatus::New,
    ]);
    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Other',
        'email' => 'other@example.com',
        'body' => 'Not a waitlist row',
        'status' => EnquiryStatus::New,
    ]);

    $this->artisan('pro:notify-waitlist')->assertSuccessful();

    Mail::assertQueued(ProSalesOpen::class, function (ProSalesOpen $mail): bool {
        return $mail->hasTo('ready@example.com');
    });
    Mail::assertNotQueued(ProSalesOpen::class, function (ProSalesOpen $mail): bool {
        return $mail->hasTo('quiet@example.com');
    });
    Mail::assertQueuedCount(1);

    $stamped = Enquiry::query()->whereRaw('LOWER(email) = ?', ['ready@example.com'])->get();
    expect($stamped)->toHaveCount(2)
        ->and($stamped->every(fn (Enquiry $row): bool => filled($row->context['sales_opened_notified_at'] ?? null)))->toBeTrue();

    expect(Enquiry::query()->where('email', 'quiet@example.com')->first()->context)->not->toHaveKey('sales_opened_notified_at');

    $this->artisan('pro:notify-waitlist')->assertSuccessful();
    Mail::assertQueuedCount(1);
});

it('lists recipients and sends nothing on a dry run', function () {
    Mail::fake();

    Enquiry::query()->create([
        'type' => EnquiryType::ProWaitlist,
        'name' => 'Guest',
        'email' => 'guest@example.com',
        'body' => 'Tell me',
        'context' => ['trigger' => 'follow_limit'],
        'status' => EnquiryStatus::New,
    ]);

    $this->artisan('pro:notify-waitlist', ['--dry' => true])
        ->expectsOutputToContain('guest@example.com')
        ->assertSuccessful();

    Mail::assertNothingQueued();
    expect(Enquiry::query()->first()->context)->not->toHaveKey('sales_opened_notified_at');
});
