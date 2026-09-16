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

    public ?string $near = null;

    public ?string $radius = null;

    public ?string $lat = null;

    public ?string $lng = null;

    public function search(): void
    {
        $params = array_filter([
            'discipline' => $this->discipline,
            'province' => $this->province,
            'from' => $this->from,
            'to' => $this->to,
            'near' => $this->near,
            'radius' => $this->radius,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        // Radius without an origin is meaningless — drop it so we
        // don't revive the old "promise we can't keep" behaviour.
        if (isset($params['radius']) && ! isset($params['near']) && ! isset($params['lat'])) {
            unset($params['radius']);
        }

        $this->redirect(route('calendar', $params), navigate: true);
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
            'radii' => [50, 100, 150, 250, 400],
        ]);
    }
}
