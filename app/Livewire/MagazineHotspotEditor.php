<?php

namespace App\Livewire;

use App\Models\MagazineHotspot;
use App\Models\MagazineIssue;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Magazines\SafeLinkUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Drag rectangles onto a magazine page and store them as manual hotspots.
 * A manual box replaces an extracted link where they overlap.
 */
class MagazineHotspotEditor extends Component
{
    public int $issueId;

    public int $page = 1;

    public ?int $editingId = null;

    public string $url = '';

    public string $label = '';

    public string $advertiser = '';

    public ?int $organisationId = null;

    public string $orgSearch = '';

    public float $x = 10;

    public float $y = 10;

    public float $w = 30;

    public float $h = 12;

    public function mount(MagazineIssue $issue): void
    {
        $this->authorizeIssue($issue);
        $this->issueId = $issue->id;
    }

    public function setBox(float $x, float $y, float $w, float $h): void
    {
        $this->x = round(max(0, min(100, $x)), 2);
        $this->y = round(max(0, min(100, $y)), 2);
        $this->w = round(max(0.5, min(100, $w)), 2);
        $this->h = round(max(0.5, min(100, $h)), 2);
    }

    public function edit(int $hotspotId): void
    {
        $hotspot = $this->issue()->hotspots()->find($hotspotId);

        if (! $hotspot instanceof MagazineHotspot) {
            return;
        }

        $this->editingId = $hotspot->id;
        $this->page = $hotspot->pdf_page;
        $this->url = $hotspot->url;
        $this->label = (string) $hotspot->label;
        $this->advertiser = (string) $hotspot->advertiser;
        $this->organisationId = $hotspot->organisation_id;
        $this->x = $hotspot->x;
        $this->y = $hotspot->y;
        $this->w = $hotspot->w;
        $this->h = $hotspot->h;
    }

    public function save(): void
    {
        $issue = $this->issue();

        $this->validate([
            'page' => ['required', 'integer', 'min:1', 'max:2000'],
            'url' => ['required', 'string', 'max:2000'],
            'label' => ['nullable', 'string', 'max:160'],
            'advertiser' => ['nullable', 'string', 'max:160'],
            'organisationId' => ['nullable', 'integer', 'exists:organisations,id'],
            'x' => ['required', 'numeric', 'between:0,100'],
            'y' => ['required', 'numeric', 'between:0,100'],
            'w' => ['required', 'numeric', 'between:0.5,100'],
            'h' => ['required', 'numeric', 'between:0.5,100'],
        ]);

        $url = SafeLinkUrl::make($this->url);

        if ($url === null) {
            $this->addError('url', 'Enter a website or email address.');

            return;
        }

        $payload = [
            'page' => $this->page,
            'pdf_page' => $this->page,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'url' => $url,
            'label' => $this->label !== '' ? $this->label : null,
            'advertiser' => $this->advertiser !== '' ? $this->advertiser : null,
            'organisation_id' => $this->organisationId,
            'source' => 'manual',
        ];

        if ($this->editingId !== null) {
            $issue->hotspots()->whereKey($this->editingId)->update($payload);
        } else {
            $issue->hotspots()->create($payload);
        }

        $this->resetBox();
    }

    public function delete(int $hotspotId): void
    {
        $this->issue()->hotspots()->whereKey($hotspotId)->where('source', 'manual')->delete();

        if ($this->editingId === $hotspotId) {
            $this->resetBox();
        }
    }

    public function resetBox(): void
    {
        $this->editingId = null;
        $this->url = '';
        $this->label = '';
        $this->advertiser = '';
        $this->organisationId = null;
        $this->x = 10;
        $this->y = 10;
        $this->w = 30;
        $this->h = 12;
    }

    public function render(): View
    {
        $issue = $this->issue();
        $hotspots = $issue->hotspots()->with('organisation')->withCount('clicks')->orderBy('page')->orderBy('id')->get();

        return view('livewire.magazine-hotspot-editor', [
            'issue' => $issue,
            'hotspots' => $hotspots,
            'pageHotspots' => $hotspots->where('pdf_page', $this->page)->values(),
            'summary' => $this->summary($hotspots),
            'organisations' => $this->organisations(),
            'pdfUrl' => $issue->pdfUrl(),
        ]);
    }

    /**
     * @param  Collection<int, MagazineHotspot>  $hotspots
     * @return list<array{name: string, clicks: int}>
     */
    private function summary(Collection $hotspots): array
    {
        $groups = [];

        foreach ($hotspots as $hotspot) {
            $name = $hotspot->displayName();
            $groups[$name] = ($groups[$name] ?? 0) + (int) $hotspot->clicks_count;
        }

        arsort($groups);

        $rows = [];

        foreach ($groups as $name => $clicks) {
            $rows[] = ['name' => $name, 'clicks' => $clicks];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Organisation>
     */
    private function organisations(): Collection
    {
        $search = trim($this->orgSearch);
        $query = Organisation::query()->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%'.addcslashes($search, '%_').'%');
        }

        return $query->limit(12)->get(['id', 'name']);
    }

    private function issue(): MagazineIssue
    {
        $issue = MagazineIssue::query()->findOrFail($this->issueId);
        $this->authorizeIssue($issue);

        return $issue;
    }

    private function authorizeIssue(MagazineIssue $issue): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User && ($user->isStaff() || $user->id === $issue->user_id),
            403,
        );
    }
}
