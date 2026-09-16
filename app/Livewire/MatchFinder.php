<?php

namespace App\Livewire;

use App\Enums\Province;
use App\Models\Discipline;
use Livewire\Component;

class MatchFinder extends Component
{
    public ?string $discipline = null;

    public ?string $province = null;

    public ?string $from = null;

    public ?string $to = null;

    /*
     * NOTE (2026-09-16, UX audit #1): the "within X km" radius dropdown
     * was removed from this finder because the site has no venue
     * coordinates and cannot honour it — the previous version submitted
     * ?radius=150 and returned all matches, breaking the headline
     * promise. Restore this property only when venue lat/lng and a real
     * Haversine scope on PublicEventQuery ship together.
     */

    public function search(): void
    {
        $this->redirect(route('calendar', array_filter([
            'discipline' => $this->discipline,
            'province' => $this->province,
            'from' => $this->from,
            'to' => $this->to,
        ], fn (mixed $value): bool => $value !== null && $value !== '')), navigate: true);
    }

    public function render()
    {
        return view('livewire.match-finder', [
            'disciplines' => Discipline::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->get(),
            'provinces' => Province::cases(),
            'indexed' => Discipline::query()->where('is_published', true)->count(),
        ]);
    }
}
