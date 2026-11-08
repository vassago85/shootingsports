<?php

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Submission;
use App\Models\Venue;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    config()->set('services.turnstile.site_key', null);
    config()->set('services.turnstile.secret_key', null);
});

it('shows a correction link on a match, a club, and a range', function () {
    $event = Event::factory()->confirmed()->create();
    $club = Organisation::factory()->create();
    $range = Venue::factory()->create();

    $this->get(route('events.show', $event))->assertOk()->assertSee('Report a correction');
    $this->get(route('clubs.show', $club))->assertOk()->assertSee('Report a correction');
    $this->get(route('ranges.show', $range->slug))->assertOk()->assertSee('Report a correction');
});

it('queues a correction about the wrong province', function () {
    $event = Event::factory()->confirmed()->create(['title' => 'Province Cup']);

    $this->from(route('events.show', $event))
        ->post(route('corrections.store'), [
            'type' => SubmissionType::Event->value,
            'subject_id' => $event->id,
            'reason' => 'wrong_province',
            'details' => 'This match is in the Western Cape, not Mpumalanga.',
            'submitter_name' => 'Ada Reporter',
            'submitter_email' => 'ada@example.com',
        ])
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('status');

    $submission = Submission::query()->first();

    expect($submission)->not->toBeNull()
        ->and($submission->type)->toBe(SubmissionType::Event)
        ->and($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->submitter_email)->toBe('ada@example.com')
        ->and($submission->payload['reason'])->toBe('wrong_province')
        ->and($submission->payload['subject_label'])->toBe('Province Cup');
});

it('accepts a correction on a club and a range', function (string $type, string $factory) {
    $subject = $factory::factory()->create();

    $this->post(route('corrections.store'), [
        'type' => $type,
        'subject_id' => $subject->id,
        'reason' => 'other',
        'details' => 'The phone number on this listing is out of date.',
        'submitter_name' => 'Bea Reporter',
        'submitter_email' => 'bea@example.com',
    ])->assertRedirect();

    expect(Submission::query()->where('type', $type)->count())->toBe(1);
})->with([
    'club' => [SubmissionType::Organisation->value, Organisation::class],
    'range' => [SubmissionType::Venue->value, Venue::class],
]);

it('rejects a correction when Turnstile is configured and the token fails', function () {
    config()->set('services.turnstile.site_key', 'site-test');
    config()->set('services.turnstile.secret_key', 'secret-test');

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
    ]);

    $event = Event::factory()->confirmed()->create();

    $this->from(route('events.show', $event))
        ->post(route('corrections.store'), [
            'type' => SubmissionType::Event->value,
            'subject_id' => $event->id,
            'reason' => 'wrong_date',
            'details' => 'The match is on the 15th, not the 14th.',
            'submitter_name' => 'Ada Reporter',
            'submitter_email' => 'ada@example.com',
            'cf-turnstile-response' => 'bad-token',
        ])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(Submission::query()->count())->toBe(0);
});
