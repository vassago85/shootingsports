<?php

namespace App\Livewire;

use App\Models\Event;
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
            $this->redirect(url('/desk/login'));

            return;
        }

        $user->ensureCalendarSlug();

        if ($this->saved) {
            $user->savedEvents()->detach($this->eventId);
            $this->saved = false;

            return;
        }

        $user->savedEvents()->syncWithoutDetaching([$this->eventId]);
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.save-to-calendar');
    }
}
