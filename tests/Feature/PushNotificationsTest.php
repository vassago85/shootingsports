<?php

use App\Enums\ClaimStatus;
use App\Enums\EventStatus;
use App\Jobs\SendPushNotification;
use App\Models\Claim;
use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Push\ExpoPushClient;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

it('posts a batch of messages to Expo and returns the ticket array', function () {
    Http::fake([
        'exp.host/*' => Http::response([
            'data' => [
                ['status' => 'ok', 'id' => 'ticket-1'],
                ['status' => 'ok', 'id' => 'ticket-2'],
            ],
        ]),
    ]);

    $client = new ExpoPushClient;
    $messages = [
        ['to' => 'ExponentPushToken[AAA]', 'title' => 'Hi', 'body' => 'Alice'],
        ['to' => 'ExponentPushToken[BBB]', 'title' => 'Hi', 'body' => 'Bob'],
    ];

    $tickets = $client->sendBatch($messages);

    expect($tickets)->toHaveCount(2)
        ->and($tickets->first()['id'])->toBe('ticket-1');

    Http::assertSent(fn ($request) => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request->method() === 'POST'
        && $request->data() === $messages);
});

it('returns an empty collection and logs when Expo is unreachable', function () {
    Http::fake(['exp.host/*' => Http::response('', 503)]);

    $tickets = (new ExpoPushClient)->sendBatch([
        ['to' => 'ExponentPushToken[AAA]', 'title' => 'x', 'body' => 'x'],
    ]);

    expect($tickets)->toBeEmpty();
});

it('surfaces the DeviceNotRegistered tokens so the job can delete them', function () {
    $messages = [
        ['to' => 'ExponentPushToken[OK]', 'title' => 'x', 'body' => 'x'],
        ['to' => 'ExponentPushToken[DEAD]', 'title' => 'x', 'body' => 'x'],
        ['to' => 'ExponentPushToken[THROTTLED]', 'title' => 'x', 'body' => 'x'],
    ];

    $tickets = collect([
        ['status' => 'ok', 'id' => 'ticket-1'],
        ['status' => 'error', 'message' => 'invalid', 'details' => ['error' => 'DeviceNotRegistered']],
        ['status' => 'error', 'message' => 'throttled', 'details' => ['error' => 'MessageRateExceeded']],
    ]);

    $dead = (new ExpoPushClient)->deadTokensFromTickets($messages, $tickets);

    expect($dead)->toBe(['ExponentPushToken[DEAD]']);
});

it('the SendPushNotification job sends every ios + android token and skips web', function () {
    Http::fake([
        'exp.host/*' => Http::response(['data' => [
            ['status' => 'ok', 'id' => 't1'],
            ['status' => 'ok', 'id' => 't2'],
        ]]),
    ]);

    $user = User::factory()->create();
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[IOS]', 'platform' => 'ios']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[AND]', 'platform' => 'android']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'web-not-real', 'platform' => 'web']);

    (new SendPushNotification(
        userId: $user->id,
        title: 'Hello',
        body: 'World',
        data: ['type' => 'test'],
    ))->handle(new ExpoPushClient);

    Http::assertSent(function ($request) {
        $tos = collect($request->data())->pluck('to')->sort()->values()->all();

        return $tos === ['ExponentPushToken[AND]', 'ExponentPushToken[IOS]']
            && count($request->data()) === 2;
    });
});

it('deletes a device token Expo marks as DeviceNotRegistered', function () {
    Http::fake([
        'exp.host/*' => Http::response(['data' => [
            ['status' => 'error', 'message' => 'invalid', 'details' => ['error' => 'DeviceNotRegistered']],
        ]]),
    ]);

    $user = User::factory()->create();
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[GONE]', 'platform' => 'ios']);

    (new SendPushNotification($user->id, 'x', 'x'))->handle(new ExpoPushClient);

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[GONE]')->exists())->toBeFalse();
});

it('does nothing when the user has no device tokens', function () {
    Http::fake();
    $user = User::factory()->create();

    (new SendPushNotification($user->id, 'x', 'x'))->handle(new ExpoPushClient);

    Http::assertNothingSent();
});

it('approving a club claim queues a claim.approved push to the claimant', function () {
    Bus::fake([SendPushNotification::class]);

    $claimant = User::factory()->create();
    $reviewer = User::factory()->staff()->create();
    $club = Organisation::factory()->create();

    $claim = Claim::query()->create([
        'claimable_type' => $club->getMorphClass(),
        'claimable_id' => $club->id,
        'user_id' => $claimant->id,
        'evidence' => 'I am the chair',
        'status' => ClaimStatus::Pending,
    ]);

    $claim->approve($reviewer);

    Bus::assertDispatched(SendPushNotification::class, function (SendPushNotification $job) use ($claimant, $claim): bool {
        return $job->userId === $claimant->id
            && $job->title === 'Claim approved'
            && $job->data['type'] === 'claim.approved'
            && $job->data['claim_id'] === $claim->id;
    });
});

it('rejecting a range claim queues a claim.rejected push to the claimant', function () {
    Bus::fake([SendPushNotification::class]);

    $claimant = User::factory()->create();
    $reviewer = User::factory()->staff()->create();
    $range = Venue::factory()->create();

    $claim = Claim::query()->create([
        'claimable_type' => $range->getMorphClass(),
        'claimable_id' => $range->id,
        'user_id' => $claimant->id,
        'evidence' => 'operator',
        'status' => ClaimStatus::Pending,
    ]);

    $claim->reject($reviewer);

    Bus::assertDispatched(SendPushNotification::class, function (SendPushNotification $job) use ($claimant): bool {
        return $job->userId === $claimant->id
            && $job->title === 'Claim rejected'
            && $job->data['type'] === 'claim.rejected';
    });
});

it('cancelling a saved event fans a push out to every saver', function () {
    Bus::fake([SendPushNotification::class]);

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);

    $saverA = User::factory()->create();
    $saverB = User::factory()->create();
    $notSaving = User::factory()->create();

    $saverA->savedEvents()->attach($event);
    $saverB->savedEvents()->attach($event);

    $event->forceFill(['status' => EventStatus::Cancelled])->save();

    Bus::assertDispatchedTimes(SendPushNotification::class, 2);
    Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $saverA->id
        && $job->data['type'] === 'event.cancelled'
        && $job->data['event_id'] === $event->id);
    Bus::assertDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $saverB->id);
    Bus::assertNotDispatched(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->userId === $notSaving->id);
});

it('does not fan out when a non-status field changes on a saved event', function () {
    Bus::fake([SendPushNotification::class]);

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);
    $saver = User::factory()->create();
    $saver->savedEvents()->attach($event);

    $event->forceFill(['title' => 'Renamed match'])->save();

    Bus::assertNotDispatched(SendPushNotification::class);
});

it('does not fan out when the status change is not one of the paged transitions', function () {
    Bus::fake([SendPushNotification::class]);

    $event = Event::factory()->create(['status' => EventStatus::Confirmed]);
    $saver = User::factory()->create();
    $saver->savedEvents()->attach($event);

    // Full is a status transition we care about operationally but not
    // one that pages the shooter — the saved match hasn't changed in a
    // way that requires action from them.
    $event->forceFill(['status' => EventStatus::Full])->save();

    Bus::assertNotDispatched(SendPushNotification::class);
});
