<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitExceeded;
use App\Models\AttendedEvent;
use App\Models\Event;
use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "I shot this" button + modal on a public match page. Snapshots the
 * event's name / date / discipline / venue / host at log time so the
 * personal log survives the event being renamed or deleted.
 *
 * All logging is server-side — a raw POST that bypasses this component
 * still hits the AttendedEvent observer, which raises PlanLimitExceeded
 * for Free users past their cap.
 */
class LogAttendance extends Component
{
    public int $eventId;

    public bool $logged = false;

    public bool $open = false;

    /** Optional fields shooters can fill in the modal before saving. */
    #[Validate('nullable|string|max:60')]
    public string $division = '';

    #[Validate('nullable|string|max:20')]
    public string $classification = '';

    #[Validate('nullable|integer|min:1|max:9999')]
    public ?int $placing = null;

    #[Validate('nullable|integer|min:1|max:9999')]
    public ?int $field_size = null;

    #[Validate('nullable|string|max:60')]
    public string $score = '';

    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
        $this->logged = auth()->id()
            ? AttendedEvent::query()
                ->where('user_id', auth()->id())
                ->where('event_id', $event->id)
                ->exists()
            : false;
    }

    public function openForm(): void
    {
        if (! auth()->user() instanceof User) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        // Hit the cap? Do not open the form — dispatch the upgrade
        // prompt instead, same UX as FollowButton.
        $user = auth()->user();
        $current = AttendedEvent::query()->where('user_id', $user->id)->count();

        if (! $user->withinLimit('attended_events_slots', $current)) {
            $this->dispatch('open-upgrade-prompt', trigger: 'attendance_log_limit');

            return;
        }

        $this->open = true;
    }

    public function cancel(): void
    {
        $this->open = false;
        $this->reset(['division', 'classification', 'placing', 'field_size', 'score', 'notes']);
    }

    public function save(): void
    {
        if (! auth()->user() instanceof User) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $this->validate();

        /** @var Event $event */
        $event = Event::query()->findOrFail($this->eventId);

        try {
            AttendedEvent::create([
                'user_id' => auth()->id(),
                'event_id' => $event->id,
                'discipline_id' => $event->discipline_id,
                'event_name_snapshot' => $event->title,
                'event_date' => $event->starts_at->toDateString(),
                'discipline_name_snapshot' => $event->discipline?->name,
                'venue_snapshot' => $event->venue?->name,
                'host_snapshot' => $event->hostDisplayName(),
                'division' => $this->division ?: null,
                'classification' => $this->classification ?: null,
                'placing' => $this->placing,
                'field_size' => $this->field_size,
                'score' => $this->score ?: null,
                'notes' => $this->notes ?: null,
            ]);
        } catch (PlanLimitExceeded $e) {
            $this->open = false;
            $this->dispatch('open-upgrade-prompt', trigger: $e->trigger);

            return;
        }

        $this->logged = true;
        $this->open = false;
        $this->reset(['division', 'classification', 'placing', 'field_size', 'score', 'notes']);

        $this->dispatch('umami-track', name: 'attendance_logged', trigger: 'match_page');
    }

    public function unlog(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        AttendedEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', $this->eventId)
            ->delete();

        $this->logged = false;
    }

    public function render()
    {
        return view('livewire.log-attendance');
    }
}
