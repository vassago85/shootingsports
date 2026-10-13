<?php

use App\Livewire\SubmitTeamEntry;
use App\Mail\TeamEntryCaptainMail;
use App\Mail\TeamEntryOrganiserMail;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\TeamEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return list<array{name: string, id_number: string, phone: string}>
 */
function teamEntryShooters(int $count = 4): array
{
    return collect(range(1, $count))->map(fn (int $number): array => [
        'name' => 'Shooter '.$number,
        'id_number' => '80010150090'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        'phone' => '082 000 000'.$number,
    ])->all();
}

beforeEach(function () {
    Mail::fake();
    Storage::fake(TeamEntry::PROOF_OF_PAYMENT_DISK);
});

it('sends a guest team entry with proof of payment to the organiser and the captain', function () {
    $event = Event::factory()->teamEntriesOpen()->create([
        'contact_email' => 'organiser@example.com',
        'entry_payment_instructions' => "EFT to CVO Marmer\nFNB 62310133632",
    ]);

    Livewire::test(SubmitTeamEntry::class, ['event' => $event])
        ->set('teamName', 'Bosveld Boere')
        ->set('captainName', 'Jan Captain')
        ->set('captainEmail', 'jan@example.com')
        ->set('captainPhone', '082 111 2222')
        ->set('members', teamEntryShooters())
        ->set('proofOfPayment', UploadedFile::fake()->create('pop.pdf', 120, 'application/pdf'))
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSee('Entry sent')
        ->assertSee('FNB 62310133632');

    $entry = TeamEntry::query()->sole();

    expect($entry->event_id)->toBe($event->id)
        ->and($entry->team_name)->toBe('Bosveld Boere')
        ->and($entry->members)->toBe(teamEntryShooters())
        ->and($entry->proof_of_payment_name)->toBe('pop.pdf')
        ->and(DB::table('team_entries')->value('members'))->not->toContain('8001015009001');

    Storage::disk(TeamEntry::PROOF_OF_PAYMENT_DISK)->assertExists($entry->proof_of_payment_path);

    Mail::assertQueued(TeamEntryOrganiserMail::class, function (TeamEntryOrganiserMail $mail) use ($entry): bool {
        $mail->assertHasAttachment(
            Attachment::fromStorageDisk(TeamEntry::PROOF_OF_PAYMENT_DISK, $entry->proof_of_payment_path)->as('pop.pdf'),
        );

        return $mail->hasTo('organiser@example.com') && ! $mail->hasReplyTo('jan@example.com');
    });
    Mail::assertQueued(TeamEntryCaptainMail::class, function (TeamEntryCaptainMail $mail): bool {
        $mail->assertSeeInHtml('How to pay')
            ->assertSeeInHtml('FNB 62310133632')
            ->assertDontSeeInHtml('organiser@example.com');

        return $mail->hasTo('jan@example.com');
    });
});

it('requires every shooter and consent before sending', function () {
    $event = Event::factory()->teamEntriesOpen()->create();
    $shooters = teamEntryShooters();
    $shooters[2]['id_number'] = '';

    Livewire::test(SubmitTeamEntry::class, ['event' => $event])
        ->set('teamName', 'Bosveld Boere')
        ->set('captainName', 'Jan Captain')
        ->set('captainEmail', 'jan@example.com')
        ->set('captainPhone', '082 111 2222')
        ->set('members', $shooters)
        ->call('submit')
        ->assertHasErrors(['members.2.id_number' => 'required', 'consent' => 'accepted']);

    expect(TeamEntry::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('sends the entry to staff when the match has no organiser email', function () {
    $staff = User::factory()->staff()->create();
    $host = Organisation::factory()->create(['email' => null]);
    $event = Event::factory()->teamEntriesOpen(2)->create([
        'contact_email' => null,
        'host_organisation_id' => $host->id,
    ]);

    Livewire::test(SubmitTeamEntry::class, ['event' => $event])
        ->set('teamName', 'Duo')
        ->set('captainName', 'Jan Captain')
        ->set('captainEmail', 'jan@example.com')
        ->set('captainPhone', '082 111 2222')
        ->set('members', teamEntryShooters(2))
        ->set('consent', true)
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertQueued(TeamEntryOrganiserMail::class, fn (TeamEntryOrganiserMail $mail): bool => $mail->hasTo($staff->email));
});

it('rejects entries when the match does not take team entries', function () {
    $event = Event::factory()->teamEntriesOpen()->create(['accepts_team_entries' => false]);

    Livewire::test(SubmitTeamEntry::class, ['event' => $event])
        ->set('teamName', 'Bosveld Boere')
        ->set('captainName', 'Jan Captain')
        ->set('captainEmail', 'jan@example.com')
        ->set('captainPhone', '082 111 2222')
        ->set('members', teamEntryShooters())
        ->set('consent', true)
        ->call('submit')
        ->assertHasErrors('teamName');

    expect(TeamEntry::query()->count())->toBe(0);
    Mail::assertNothingQueued();

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertDontSee('Enter your team');
});

it('drops submissions that fill the honeypot', function () {
    $event = Event::factory()->teamEntriesOpen()->create();

    Livewire::test(SubmitTeamEntry::class, ['event' => $event])
        ->set('companyWebsite', 'https://spam.example')
        ->set('teamName', 'Bosveld Boere')
        ->set('captainName', 'Jan Captain')
        ->set('captainEmail', 'jan@example.com')
        ->set('captainPhone', '082 111 2222')
        ->set('members', teamEntryShooters())
        ->set('consent', true)
        ->call('submit')
        ->assertHasErrors('teamName');

    expect(TeamEntry::query()->count())->toBe(0);
});

it('shows the team entry form on an open match page', function () {
    $event = Event::factory()->teamEntriesOpen()->create();

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('Enter your team')
        ->assertSee('Shooter 4')
        ->assertDontSee('Shooter 5');
});
