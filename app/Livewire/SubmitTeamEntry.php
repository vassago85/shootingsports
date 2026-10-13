<?php

namespace App\Livewire;

use App\Mail\TeamEntryCaptainMail;
use App\Mail\TeamEntryOrganiserMail;
use App\Models\Event;
use App\Models\TeamEntry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmitTeamEntry extends Component
{
    use WithFileUploads;

    public int $eventId;

    public string $teamName = '';

    public string $captainName = '';

    public string $captainEmail = '';

    public string $captainPhone = '';

    /** @var list<array{name: string, id_number: string, phone: string}> */
    public array $members = [];

    /** @var UploadedFile|null */
    public $proofOfPayment = null;

    public bool $consent = false;

    public string $companyWebsite = '';

    public bool $submitted = false;

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
        $this->members = array_fill(0, max(1, (int) $event->team_size), ['name' => '', 'id_number' => '', 'phone' => '']);

        $user = auth()->user();

        if ($user instanceof User) {
            $this->captainName = (string) $user->name;
            $this->captainEmail = (string) $user->email;
        }
    }

    public function submit(): void
    {
        $event = $this->event();

        if (! $event->openForTeamEntries()) {
            $this->addError('teamName', 'Entries are not open on this site for this match.');

            return;
        }

        if ($this->companyWebsite !== '') {
            $this->addError('teamName', 'Unable to submit this entry.');

            return;
        }

        $key = 'team-entry:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('teamName', 'Too many entries from this network. Please try again later.');

            return;
        }

        $validated = $this->validate([
            'teamName' => ['required', 'string', 'max:120'],
            'captainName' => ['required', 'string', 'max:120'],
            'captainEmail' => ['required', 'email', 'max:255'],
            'captainPhone' => ['required', 'string', 'max:40'],
            'members' => ['required', 'array', 'size:'.$event->team_size],
            'members.*.name' => ['required', 'string', 'max:120'],
            'members.*.id_number' => ['required', 'string', 'min:6', 'max:20'],
            'members.*.phone' => ['required', 'string', 'max:40'],
            'proofOfPayment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Confirm that every shooter agreed to share their details with the organiser.',
        ], [
            'members.*.name' => 'shooter name',
            'members.*.id_number' => 'ID number',
            'members.*.phone' => 'contact number',
        ]);

        RateLimiter::hit($key, 60 * 15);

        $proofPath = null;
        $proofName = null;

        if ($this->proofOfPayment instanceof UploadedFile) {
            $proofPath = $this->proofOfPayment->store('team-entries/'.$event->id, TeamEntry::PROOF_OF_PAYMENT_DISK);
            $proofName = $this->proofOfPayment->getClientOriginalName();
        }

        $entry = $event->teamEntries()->create([
            'team_name' => $validated['teamName'],
            'captain_name' => $validated['captainName'],
            'captain_email' => $validated['captainEmail'],
            'captain_phone' => $validated['captainPhone'],
            'members' => array_map(fn (array $member): array => [
                'name' => trim($member['name']),
                'id_number' => trim($member['id_number']),
                'phone' => trim($member['phone']),
            ], array_values($validated['members'])),
            'proof_of_payment_path' => $proofPath ?: null,
            'proof_of_payment_name' => $proofName,
            'ip_address' => request()->ip(),
        ]);

        $this->deliver($event, $entry);

        $this->submitted = true;
    }

    public function render(): View
    {
        $event = $this->event();

        return view('livewire.submit-team-entry', [
            'event' => $event,
            'open' => $event->openForTeamEntries(),
        ]);
    }

    /**
     * The organiser gets the entry. Staff step in only when the match
     * has no organiser address, so ID numbers are not copied around.
     */
    private function deliver(Event $event, TeamEntry $entry): void
    {
        $organiserEmail = $event->organiserEmail();

        $recipients = $organiserEmail !== null
            ? [$organiserEmail]
            : User::query()->where('is_staff', true)->whereNotNull('email')->pluck('email')->all();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new TeamEntryOrganiserMail($entry));
        }

        Mail::to($entry->captain_email)->queue(new TeamEntryCaptainMail($entry));
    }

    private function event(): Event
    {
        return Event::query()->with('hostOrganisation')->findOrFail($this->eventId);
    }
}
