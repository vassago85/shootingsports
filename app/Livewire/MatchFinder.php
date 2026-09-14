<?php

namespace App\Livewire;

use App\Enums\Province;
use App\Models\Discipline;
use Livewire\Component;

class MatchFinder extends Component
{
    public ?string $discipline = null;

    public ?string $province = null;

    public ?string $radius = null;

    public ?string $from = null;

    public ?string $to = null;

    public function search(): void
    {
        $this->redirect(route('calendar', array_filter([
            'discipline' => $this->discipline,
            'province' => $this->province,
            'radius' => $this->radius,
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
