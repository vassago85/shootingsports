<?php

namespace App\Models;

use App\Enums\Division;
use App\Enums\PlacementSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'provider_id', 'ad_slot_id', 'slot', 'division', 'headline', 'body', 'image_path', 'click_url',
    'targeting', 'starts_on', 'ends_on', 'rate_cents', 'impressions', 'clicks', 'is_active',
])]
class Placement extends Model
{
    protected function casts(): array
    {
        return [
            'slot' => PlacementSlot::class,
            'division' => Division::class,
            'targeting' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'rate_cents' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }

    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class);
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image_path)) {
            return null;
        }

        return Storage::disk('media')->url($this->image_path);
    }

    public function destinationUrl(): ?string
    {
        return $this->click_url
            ?: $this->provider?->website_url
            ?: ($this->provider ? route('suppliers.show', $this->provider->slug) : null);
    }

    /**
     * Count one view of this advert. A placement rendered twice on the
     * same page counts once. Automated clients are skipped so a crawler
     * does not fill the counter.
     */
    public function recordImpression(): void
    {
        $seen = request()->attributes->get('placement_impressions', []);

        if (isset($seen[$this->id])) {
            return;
        }

        $seen[$this->id] = true;
        request()->attributes->set('placement_impressions', $seen);

        if ($this->requestIsAutomated()) {
            return;
        }

        $this->increment('impressions');
    }

    private function requestIsAutomated(): bool
    {
        $agent = strtolower((string) request()->userAgent());

        foreach (['bot', 'crawler', 'spider', 'slurp', 'preview'] as $marker) {
            if (str_contains($agent, $marker)) {
                return true;
            }
        }

        return false;
    }

    public function scopeActiveOn(Builder $query, ?string $date = null): Builder
    {
        $date ??= now('Africa/Johannesburg')->toDateString();

        return $query
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }
}
