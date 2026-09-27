<?php

use App\Mail\EnquiryForwardedMail;
use App\Mail\EnquiryReceivedMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->withoutVite();
});

it('forwards an event enquiry to the organiser email and still tells staff', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $event = Event::factory()->create([
        'title' => '2 Man Gong Challenge',
        'contact_email' => 'organiser@club.test',
    ]);

    $this->post(route('enquiries.store'), [
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'phone' => '0820000000',
        'subject' => 'Entry link',
        'body' => 'Can you send me a link for the entry please.',
        'type' => 'listing',
        'about_type' => 'event',
        'about_id' => $event->id,
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ])->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));

    Mail::assertQueued(EnquiryForwardedMail::class, function (EnquiryForwardedMail $mail): bool {
        return $mail->hasTo('organiser@club.test')
            && $mail->hasReplyTo('mark@example.test')
            && str_contains($mail->render(), 'Can you send me a link for the entry please.');
    });
});

it('does not forward an event enquiry when the event has no organiser email', function () {
    Mail::fake();

    $event = Event::factory()->create(['contact_email' => null]);

    $this->post(route('enquiries.store'), [
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Can you send me a link for the entry please.',
        'type' => 'listing',
        'about_type' => 'event',
        'about_id' => $event->id,
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ])->assertRedirect(route('enquiries.thanks'));

    Mail::assertNotQueued(EnquiryForwardedMail::class);
});

it('lists an ask link on the event page and hides the organiser email', function () {
    $event = Event::factory()->create([
        'title' => '2 Man Gong Challenge',
        'contact_email' => 'organiser@club.test',
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Ask the organiser')
        ->assertSee(route('enquiries.listing', ['type' => 'event', 'id' => $event->id]), false)
        ->assertDontSee('organiser@club.test');
});
