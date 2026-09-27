<?php

namespace App\Livewire;

use App\Enums\EntryCollection;
use App\Enums\EntryPaymentStatus;
use App\Enums\EntryStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use Illuminate\Support\Str;
use Livewire\Component;

class EnterEvent extends Component
{
    public int $eventId;

    public string $division = '';

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
    }

    public function enter(PaystackClient $paystack): mixed
    {
        $user = auth()->user();
        $event = $this->event();

        if (! $user instanceof User) {
            return $this->redirect(route('login'), navigate: false);
        }

        if (! $event->openForPlatformEntries()) {
            $this->addError('division', 'Entries are not open on this site for this event.');

            return null;
        }

        $existing = $event->entries()->where('user_id', $user->id)->first();

        if ($existing instanceof EventEntry && $existing->status === EntryStatus::Entered) {
            return null;
        }

        if ($event->isFull()) {
            $this->addError('division', 'This event is full.');

            return null;
        }

        if ($event->entry_collection === EntryCollection::Paystack) {
            return $this->startPayment($paystack, $event, $user, $existing);
        }

        EventEntry::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'status' => EntryStatus::Entered,
                'division' => $this->division !== '' ? $this->division : null,
                'payment_status' => EntryPaymentStatus::External,
                'amount_cents' => null,
                'payment_reference' => null,
                'paid_at' => null,
            ],
        );

        return null;
    }

    public function withdraw(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->event()->entries()
            ->where('user_id', $user->id)
            ->where('status', EntryStatus::Entered)
            ->update(['status' => EntryStatus::Withdrawn]);
    }

    public function render()
    {
        $event = $this->event();
        $user = auth()->user();
        $entry = $user instanceof User
            ? $event->entries()->where('user_id', $user->id)->first()
            : null;

        return view('livewire.enter-event', [
            'event' => $event,
            'entry' => $entry,
            'open' => $event->openForPlatformEntries(),
            'full' => $event->isFull() && ! ($entry instanceof EventEntry && $entry->status === EntryStatus::Entered),
        ]);
    }

    private function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    private function startPayment(PaystackClient $paystack, Event $event, User $user, ?EventEntry $existing): mixed
    {
        $amount = (int) $event->entry_fee_cents;

        if ($amount < 100 || blank(config('services.paystack.secret_key'))) {
            $this->addError('division', 'Online payment is not available for this event yet. Pay the club directly.');

            return null;
        }

        if ($event->status !== EventStatus::EntriesOpen) {
            $this->addError('division', 'Entries are not open.');

            return null;
        }

        $reference = 'entry-'.$event->id.'-'.$user->id.'-'.Str::lower(Str::random(8));

        EventEntry::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'status' => EntryStatus::PendingPayment,
                'division' => $this->division !== '' ? $this->division : ($existing?->division),
                'payment_status' => EntryPaymentStatus::Pending,
                'amount_cents' => $amount,
                'payment_reference' => $reference,
                'paid_at' => null,
            ],
        );

        $init = $paystack->initializeOneOffTransaction(
            email: $user->email,
            amountCents: $amount,
            callbackUrl: route('paystack.callback'),
            reference: $reference,
            metadata: [
                'purpose' => 'event_entry',
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
        );

        return redirect()->away($init['authorization_url']);
    }
}
