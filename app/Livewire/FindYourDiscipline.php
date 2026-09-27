<?php

namespace App\Livewire;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Organisation;
use App\Queries\PublicEventQuery;
use App\Support\DisciplineRecommender;
use Livewire\Component;

class FindYourDiscipline extends Component
{
    public int $step = 1;

    public string $firearm = '';

    public string $pace = '';

    public string $setting = '';

    public string $distance = '';

    public string $company = '';

    public string $budget = '';

    public string $province = '';

    public function choose(string $field, string $value): void
    {
        if (! in_array($field, ['firearm', 'pace', 'setting', 'distance', 'company', 'budget'], true)) {
            return;
        }

        $this->{$field} = $value;
        $this->step = min(7, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function render(DisciplineRecommender $recommender)
    {
        $slugs = [];
        $sports = collect();
        $clubs = collect();
        $events = collect();

        if ($this->step >= 7 && $this->firearm !== '') {
            $slugs = $recommender->recommend([
                'firearm' => $this->firearm,
                'pace' => $this->pace,
                'setting' => $this->setting,
                'distance' => $this->distance,
                'company' => $this->company,
                'budget' => $this->budget,
            ]);

            $sports = Discipline::query()->whereIn('slug', $slugs)->where('is_published', true)->get()
                ->sortBy(fn (Discipline $discipline): int => array_search($discipline->slug, $slugs, true) ?: 0)
                ->values();

            if ($this->province !== '') {
                $clubs = Organisation::query()
                    ->published()
                    ->where('province', $this->province)
                    ->whereHas('disciplines', fn ($query) => $query->whereIn('slug', $slugs))
                    ->orderBy('name')
                    ->limit(6)
                    ->get();
            }

            $events = (new PublicEventQuery(
                provinceSlug: $this->province !== '' ? Province::tryFrom($this->province)?->urlSlug() : null,
                disciplineIds: $sports->pluck('id')->all(),
                limit: 6,
            ))->get();
        }

        return view('livewire.find-your-discipline', [
            'sports' => $sports,
            'clubs' => $clubs,
            'events' => $events,
            'provinces' => Province::cases(),
        ])->layout('components.layouts.public', [
            'title' => 'Find your discipline',
        ]);
    }
}
