<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Mail\EnquiryFollowUpMail;
use App\Mail\EnquiryForwardedMail;
use App\Mail\EnquiryReceivedMail;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Services\Enquiries\RecordEnquiryReply;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('forwards an event enquiry to the organiser from shooting sports and keeps a staff copy', function () {
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
            && $mail->envelope()->from->address === config('mail.from.address')
            && $mail->envelope()->replyTo === []
            && $mail->envelope()->cc === []
            && $mail->copyEnquirer === false
            && str_contains($mail->render(), 'Can you send me a link for the entry please.')
            && str_contains($mail->render(), 'sent through Shooting Sports');
    });
});

it('lets staff resend an enquiry to the organiser with the enquirer copied', function () {
    Mail::fake();

    $staff = User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);
    $event = Event::factory()->create([
        'title' => '2 Man Gong Challenge',
        'contact_email' => 'organiser@club.test',
    ]);
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Can you send me a link for the entry please.',
        'about_type' => 'event',
        'about_id' => $event->id,
        'status' => EnquiryStatus::Read,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
        ->callAction('forward_to_organiser')
        ->assertNotified();

    Mail::assertQueued(EnquiryForwardedMail::class, function (EnquiryForwardedMail $mail): bool {
        $cc = $mail->envelope()->cc;

        return $mail->hasTo('organiser@club.test')
            && $mail->copyEnquirer === true
            && $mail->envelope()->replyTo === []
            && count($cc) === 1
            && $cc[0]->address === 'mark@example.test'
            && str_contains($mail->render(), 'Mark Kalell is copied on this email');
    });

    expect($enquiry->fresh()->status)->toBe(EnquiryStatus::Read);
});

it('hides the forward action when the event and host club have no email', function () {
    $staff = User::factory()->staff()->create();
    $club = Organisation::factory()->create(['email' => null]);
    $event = Event::factory()->create([
        'contact_email' => null,
        'host_organisation_id' => $club->id,
    ]);
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Can you send me a link for the entry please.',
        'about_type' => 'event',
        'about_id' => $event->id,
        'status' => EnquiryStatus::Read,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
        ->assertActionHidden('forward_to_organiser');
});

it('skips the enquirer cc when the organiser is the same address', function () {
    Mail::fake();

    $staff = User::factory()->staff()->create();
    $event = Event::factory()->create([
        'title' => 'Self-run match',
        'contact_email' => 'mark@example.test',
    ]);
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Testing my own form.',
        'about_type' => 'event',
        'about_id' => $event->id,
        'status' => EnquiryStatus::Read,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
        ->callAction('forward_to_organiser')
        ->assertNotified();

    Mail::assertQueued(EnquiryForwardedMail::class, function (EnquiryForwardedMail $mail): bool {
        return $mail->hasTo('mark@example.test')
            && $mail->envelope()->cc === [];
    });
});

it('sends an event enquiry to the host club when the match has no email', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $club = Organisation::factory()->create([
        'email' => 'club@example.test',
    ]);
    $event = Event::factory()->create([
        'contact_email' => null,
        'host_organisation_id' => $club->id,
    ]);

    $this->post(route('enquiries.store'), enquiryPayload([
        'about_type' => 'event',
        'about_id' => $event->id,
    ]))->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));
    Mail::assertQueued(EnquiryForwardedMail::class, fn (EnquiryForwardedMail $mail): bool => $mail->hasTo('club@example.test'));
});

it('tells staff when an event and its host club have no email', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $club = Organisation::factory()->create(['email' => null]);
    $event = Event::factory()->create([
        'contact_email' => null,
        'host_organisation_id' => $club->id,
    ]);

    $this->post(route('enquiries.store'), enquiryPayload([
        'about_type' => 'event',
        'about_id' => $event->id,
    ]))->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));
    Mail::assertNotQueued(EnquiryForwardedMail::class);
});

it('sends a club enquiry only to the club', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $club = Organisation::factory()->create([
        'name' => 'Postal Club',
        'email' => 'secretary@club.test',
    ]);

    $this->post(route('enquiries.store'), enquiryPayload([
        'about_type' => 'organisation',
        'about_id' => $club->id,
    ]))->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));
    Mail::assertQueued(EnquiryForwardedMail::class, fn (EnquiryForwardedMail $mail): bool => $mail->hasTo('secretary@club.test'));
});

it('tells staff when a club has no email', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $club = Organisation::factory()->create(['email' => null]);

    $this->post(route('enquiries.store'), enquiryPayload([
        'about_type' => 'organisation',
        'about_id' => $club->id,
    ]))->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));
    Mail::assertNotQueued(EnquiryForwardedMail::class);
});

it('sends a business enquiry only to the business', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $provider = Provider::factory()->create([
        'email' => 'shop@example.test',
    ]);

    $this->post(route('enquiries.store'), enquiryPayload([
        'about_type' => 'provider',
        'about_id' => $provider->id,
    ]))->assertRedirect(route('enquiries.thanks'));

    Mail::assertQueued(EnquiryReceivedMail::class, fn (EnquiryReceivedMail $mail): bool => $mail->hasTo('staff@shootingsports.test'));
    Mail::assertQueued(EnquiryForwardedMail::class, fn (EnquiryForwardedMail $mail): bool => $mail->hasTo('shop@example.test'));
});

it('sends a later reply to the club and back through shooting sports', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $club = Organisation::factory()->create(['email' => 'secretary@club.test']);
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Can you send me a link for the entry please.',
        'about_type' => 'organisation',
        'about_id' => $club->id,
        'status' => EnquiryStatus::Replied,
        'reply_token' => str_repeat('a', 48),
    ]);

    app(RecordEnquiryReply::class)->fromEnquirer($enquiry, 'Here is the extra detail you asked for.');

    Mail::assertQueued(EnquiryFollowUpMail::class, function (EnquiryFollowUpMail $mail): bool {
        return $mail->hasTo('secretary@club.test')
            && $mail->forStaff === false
            && $mail->envelope()->replyTo === [];
    });
    Mail::assertQueued(EnquiryFollowUpMail::class, function (EnquiryFollowUpMail $mail): bool {
        return $mail->hasTo('staff@shootingsports.test') && $mail->forStaff;
    });
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function enquiryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mark Kalell',
        'email' => 'mark@example.test',
        'body' => 'Can you send me a link for the entry please.',
        'type' => 'listing',
        'company_website' => '',
        'form_loaded_at' => time() - 10,
    ], $overrides);
}
