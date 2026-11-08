<?php

namespace App\Livewire;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Event;
use App\Models\SavedEvent;
use Livewire\Component;

class SaveToCalendar extends Component
{
    public int $eventId;

    public bool $saved = false;

    public string $variant = 'bar';

    public function mount(Event $event): void
    {
        $this->eventId = $event->id;
        $this->saved = auth()->user()?->savedEvents()->where('events.id', $event->id)->exists() ?? false;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirect(route('login'));

            return;
        }

        $user->ensureCalendarSlug();

        if ($this->saved) {
            $user->savedEvents()->detach($this->eventId);
            $this->saved = false;

            return;
        }

        try {
            SavedEvent::create([
                'user_id' => $user->id,
                'event_id' => $this->eventId,
            ]);
        } catch (PlanLimitExceeded $e) {
            $this->dispatch('open-upgrade-prompt', trigger: $e->trigger);

            return;
        }

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.save-to-calendar');
    }
}
