<?php

namespace App\Livewire;

use App\Enums\DisciplineFamily;
use App\Queries\PublicEventQuery;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class CalendarFilter extends Component
{
    #[Url]
    public string $family = 'all';

    #[Url]
    public bool $novice = false;

    #[Url]
    public bool $confirmed = false;

    #[Url]
    public ?string $discipline = null;

    #[Url]
    public ?string $province = null;

    #[Url]
    public ?string $radius = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public ?int $limit = null;

    public bool $showMore = false;

    public function setFamily(string $family): void
    {
        $this->family = $family;
    }

    public function toggleNovice(): void
    {
        $this->novice = ! $this->novice;
    }

    public function toggleConfirmed(): void
    {
        $this->confirmed = ! $this->confirmed;
    }

    public function render()
    {
        $query = new PublicEventQuery(
            family: $this->family,
            disciplineSlug: $this->discipline,
            provinceSlug: $this->province,
            radiusKm: $this->radius ? (int) $this->radius : null,
            from: $this->safeDate($this->from),
            to: $this->safeDate($this->to),
            novice: $this->novice,
            confirmedOnly: $this->confirmed,
            limit: $this->limit,
        );

        return view('livewire.calendar-filter', [
            'events' => $query->get(),
            'families' => DisciplineFamily::cases(),
        ]);
    }

    private function safeDate(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
