<?php

namespace App\Livewire;

use App\Enums\Division;
use App\Enums\Province;
use App\Exceptions\PlanLimitExceeded;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\SavedSearch;
use App\Models\User;
use App\Queries\PublicEventQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class CalendarMonth extends Component
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
    public ?string $near = null;

    #[Url]
    public ?string $lat = null;

    #[Url]
    public ?string $lng = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?string $month = null;

    public function mount(): void
    {
        if (! filled($this->month) || ! preg_match('/^\d{4}-\d{2}$/', (string) $this->month)) {
            $this->month = filled($this->from)
                ? Carbon::parse($this->from)->format('Y-m')
                : now()->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
    }

    public function goToday(): void
    {
        $this->month = now()->format('Y-m');
    }

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

    public function toggleProvince(string $slug): void
    {
        $selected = $this->selectedProvinceSlugs();

        if (in_array($slug, $selected, true)) {
            $selected = array_values(array_diff($selected, [$slug]));
        } else {
            $selected[] = $slug;
        }

        $this->province = $selected === [] ? null : implode(',', $selected);
    }

    public function clearProvinces(): void
    {
        $this->province = null;
    }

    public function clearFamily(): void
    {
        $this->family = 'all';
    }

    public function clearNovice(): void
    {
        $this->novice = false;
    }

    public function clearConfirmed(): void
    {
        $this->confirmed = false;
    }

    public function clearDiscipline(): void
    {
        $this->discipline = null;
    }

    public function clearDates(): void
    {
        $this->from = null;
        $this->to = null;
    }

    public function clearDistance(): void
    {
        $this->near = null;
        $this->lat = null;
        $this->lng = null;
        $this->radius = null;
    }

    public function clearAll(): void
    {
        $this->family = 'all';
        $this->novice = false;
        $this->confirmed = false;
        $this->discipline = null;
        $this->province = null;
        $this->radius = null;
        $this->near = null;
        $this->lat = null;
        $this->lng = null;
        $this->from = null;
        $this->to = null;
    }

    public function hasActiveFilters(): bool
    {
        return $this->family !== 'all'
            || $this->novice
            || $this->confirmed
            || filled($this->discipline)
            || filled($this->province)
            || filled($this->radius)
            || filled($this->near)
            || filled($this->lat)
            || filled($this->from)
            || filled($this->to);
    }

    public function activeDisciplineLabel(): ?string
    {
        if (! filled($this->discipline)) {
            return null;
        }

        $name = Discipline::query()
            ->where('slug', $this->discipline)
            ->value('name');

        return $name ?: $this->discipline;
    }

    public function saveSearch(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(route('login'));

            return;
        }

        try {
            SavedSearch::create([
                'user_id' => $user->id,
                'name' => $this->searchName(),
                'params' => array_filter([
                    'family' => $this->family !== 'all' ? $this->family : null,
                    'novice' => $this->novice ?: null,
                    'confirmed' => $this->confirmed ?: null,
                    'discipline' => $this->discipline,
                    'province' => $this->province,
                    'radius' => $this->radius,
                    'near' => $this->near,
                    'lat' => $this->lat,
                    'lng' => $this->lng,
                    'from' => $this->from,
                    'to' => $this->to,
                ], static fn ($v): bool => $v !== null && $v !== false && $v !== ''),
            ]);

            $this->dispatch('search-saved');
        } catch (PlanLimitExceeded $e) {
            $this->dispatch('open-upgrade-prompt', trigger: $e->trigger);
        }
    }

    private function searchName(): string
    {
        $bits = array_filter([
            $this->family !== 'all' ? ucfirst($this->family) : null,
            $this->discipline,
            $this->province ? str_replace(',', ' + ', $this->province) : null,
        ]);

        return $bits === [] ? 'All matches' : implode(' · ', $bits);
    }

    /**
     * @return list<string>
     */
    public function selectedProvinceSlugs(): array
    {
        if (! filled($this->province)) {
            return [];
        }

        return collect(preg_split('/\s*,\s*/', $this->province) ?: [])
            ->filter()
            ->values()
            ->all();
    }

    public function render()
    {
        $monthStart = Carbon::createFromFormat('Y-m', (string) $this->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $from = $this->safeDate($this->from);
        $to = $this->safeDate($this->to);

        $windowFrom = $from && $from->gt($monthStart) ? $from : $monthStart->copy();
        $windowTo = $to && $to->lt($monthEnd) ? $to : $monthEnd->copy();

        $query = new PublicEventQuery(
            family: $this->family,
            disciplineSlug: $this->discipline,
            provinceSlug: $this->province,
            radiusKm: $this->radius ? (int) $this->radius : null,
            nearLat: filled($this->lat) ? (float) $this->lat : null,
            nearLng: filled($this->lng) ? (float) $this->lng : null,
            near: $this->near,
            from: $windowFrom,
            to: $windowTo,
            novice: $this->novice,
            confirmedOnly: $this->confirmed,
        );

        $events = $query->get();

        $tz = (string) config('app.timezone');
        /** @var Collection<string, Collection<int, Event>> $byDay */
        $byDay = collect();

        foreach ($events as $event) {
            $startDay = $event->starts_at->timezone($tz)->startOfDay();
            $endDay = $event->ends_at?->timezone($tz)->startOfDay() ?? $startDay->copy();

            if ($endDay->lt($startDay)) {
                $endDay = $startDay->copy();
            }

            // A bad ends_at years out must not paint the match on every cell.
            if ($startDay->diffInDays($endDay) > 14) {
                $endDay = $startDay->copy()->addDays(14);
            }

            $cursor = $startDay->copy();

            while ($cursor->lte($endDay)) {
                $key = $cursor->format('Y-m-d');
                $bucket = $byDay->get($key, collect());
                $byDay->put($key, $bucket->push($event));
                $cursor = $cursor->addDay();
            }
        }

        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $key = $cursor->format('Y-m-d');
            $inMonth = $cursor->month === $monthStart->month;
            $days[] = [
                'date' => $cursor->copy(),
                'key' => $key,
                'inMonth' => $inMonth,
                'isToday' => $cursor->isToday(),
                'events' => $inMonth ? ($byDay->get($key) ?? collect()) : collect(),
            ];
            $cursor->addDay();
        }

        return view('livewire.calendar-month', [
            'days' => $days,
            'monthLabel' => $monthStart->format('F Y'),
            'events' => $events,
            'families' => Division::publicCases(),
            'provinces' => Province::cases(),
            'selectedProvinces' => $this->selectedProvinceSlugs(),
            'activeDisciplineLabel' => $this->activeDisciplineLabel(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'resultCount' => $events->count(),
            'radii' => [50, 100, 150, 250, 400],
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
