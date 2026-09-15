<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitExceeded;
use App\Models\AttendedEvent;
use App\Models\Discipline;
use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "Add a match that is not in our calendar" — for shooters logging
 * historical events (pre-app, out-of-country matches, informal club
 * shoots that never got listed). All fields except date + name are
 * optional so the form does not feel like paperwork.
 */
class AddManualAttendance extends Component
{
    public bool $open = false;

    #[Validate('required|string|max:180')]
    public string $event_name = '';

    #[Validate('required|date|before_or_equal:today')]
    public string $event_date = '';

    #[Validate('nullable|integer|exists:disciplines,id')]
    public ?int $discipline_id = null;

    #[Validate('nullable|string|max:120')]
    public string $venue = '';

    #[Validate('nullable|string|max:120')]
    public string $host = '';

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

    public function openForm(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $current = AttendedEvent::query()->where('user_id', $user->id)->count();

        if (! $user->withinLimit('attended_events_slots', $current)) {
            $this->dispatch('open-upgrade-prompt', trigger: 'attendance_log_limit');

            return;
        }

        $this->reset();
        $this->open = true;
        $this->event_date = now()->format('Y-m-d');
    }

    public function cancel(): void
    {
        $this->reset();
    }

    public function save(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $this->validate();

        $discipline = $this->discipline_id ? Discipline::query()->find($this->discipline_id) : null;

        try {
            AttendedEvent::create([
                'user_id' => $user->id,
                'event_id' => null,
                'discipline_id' => $discipline?->id,
                'event_name_snapshot' => trim($this->event_name),
                'event_date' => $this->event_date,
                'discipline_name_snapshot' => $discipline?->name,
                'venue_snapshot' => $this->venue ?: null,
                'host_snapshot' => $this->host ?: null,
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

        $this->reset();
        $this->dispatch('umami-track', name: 'attendance_logged', trigger: 'my_log_manual');
        $this->dispatch('attendance-added');
    }

    public function render()
    {
        return view('livewire.add-manual-attendance', [
            'disciplines' => Discipline::query()
                ->where('is_published', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
