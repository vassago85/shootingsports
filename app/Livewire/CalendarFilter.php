<?php

namespace App\Livewire;

use App\Enums\DisciplineFamily;
use App\Enums\Province;
use App\Exceptions\PlanLimitExceeded;
use App\Models\SavedSearch;
use App\Models\User;
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

    /**
     * Persist the current filter set as a SavedSearch for the authed
     * user. Anonymous users get bounced to login; free users over the
     * saved-search cap get the UpgradePrompt instead of a toast.
     */
    public function saveSearch(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirect(url('/desk/login'));

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
            'provinces' => Province::cases(),
            'selectedProvinces' => $this->selectedProvinceSlugs(),
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
