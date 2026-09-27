<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Mail\EnquiryFollowUpMail;
use App\Mail\EnquiryReplyMail;
use App\Models\Enquiry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('lets staff reply from the enquiry and emails the sender a link back', function () {
    Mail::fake();

    $staff = User::factory()->staff()->create();
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'subject' => 'List our club',
        'body' => 'Please tell me how to list our club.',
        'status' => EnquiryStatus::New,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
        ->callAction('reply', data: [
            'body' => 'Send the club name and province and we will list it.',
        ])
        ->assertNotified();

    $enquiry->refresh();

    expect($enquiry->status)->toBe(EnquiryStatus::Replied)
        ->and($enquiry->reply_token)->not->toBeNull()
        ->and($enquiry->replies)->toHaveCount(1)
        ->and($enquiry->replies->first()->body)->toBe('Send the club name and province and we will list it.')
        ->and($enquiry->replies->first()->from_enquirer)->toBeFalse();

    Mail::assertQueued(EnquiryReplyMail::class, function (EnquiryReplyMail $mail) use ($enquiry): bool {
        return $mail->hasTo('alex@example.test')
            && str_contains($mail->render(), route('enquiries.thread', $enquiry->reply_token));
    });
});

it('hides the reply action until a pre-launch enquiry is confirmed', function () {
    $staff = User::factory()->staff()->create();
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::PrelaunchContributor,
        'name' => 'Pat',
        'email' => 'pat@example.test',
        'body' => 'I want to help.',
        'status' => EnquiryStatus::PendingConfirmation,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
        ->assertActionHidden('reply');
});

it('shows the thread on the site and stores an answer from the sender', function () {
    Mail::fake();

    User::factory()->staff()->create(['email' => 'staff@shootingsports.test']);

    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'subject' => 'List our club',
        'body' => 'Please tell me how to list our club.',
        'status' => EnquiryStatus::Replied,
        'reply_token' => 'threadtoken123',
    ]);
    $enquiry->replies()->create([
        'from_enquirer' => false,
        'body' => 'Send the club name and province.',
    ]);

    $this->get(route('enquiries.thread', 'threadtoken123'))
        ->assertOk()
        ->assertSee('Please tell me how to list our club.')
        ->assertSee('Send the club name and province.')
        ->assertSee('Shooting Sports')
        ->assertDontSee('<script>alert(1)</script>', false);

    $this->post(route('enquiries.thread.reply', 'threadtoken123'), [
        'body' => '<script>alert(1)</script> The club is in Gauteng.',
        'company_website' => '',
    ])->assertRedirect(route('enquiries.thread', 'threadtoken123'));

    $enquiry->refresh();

    expect($enquiry->status)->toBe(EnquiryStatus::New)
        ->and($enquiry->read_at)->toBeNull()
        ->and($enquiry->replies)->toHaveCount(2);

    $this->get(route('enquiries.thread', 'threadtoken123'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('The club is in Gauteng.');

    Mail::assertQueued(EnquiryFollowUpMail::class, function (EnquiryFollowUpMail $mail): bool {
        return $mail->hasTo('staff@shootingsports.test');
    });
});

it('rejects a missing thread and a honeypot reply', function () {
    $enquiry = Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'body' => 'Please tell me how to list our club.',
        'status' => EnquiryStatus::Replied,
        'reply_token' => 'threadtoken123',
    ]);

    $this->get(route('enquiries.thread', 'missingtoken'))->assertNotFound();

    $this->from(route('enquiries.thread', 'threadtoken123'))
        ->post(route('enquiries.thread.reply', 'threadtoken123'), [
            'body' => 'This is a real looking reply.',
            'company_website' => 'https://spam.test',
        ])
        ->assertSessionHasErrors('body');

    expect($enquiry->fresh()->replies)->toHaveCount(0);
});

it('keeps the reply thread open while the coming-soon gate is on', function () {
    config()->set('coming-soon.enabled', true);

    Enquiry::query()->create([
        'type' => EnquiryType::General,
        'name' => 'Alex Shooter',
        'email' => 'alex@example.test',
        'body' => 'Please tell me how to list our club.',
        'status' => EnquiryStatus::Replied,
        'reply_token' => 'threadtoken123',
    ]);

    $this->get(route('enquiries.thread', 'threadtoken123'))
        ->assertOk()
        ->assertSee('Please tell me how to list our club.');
});
