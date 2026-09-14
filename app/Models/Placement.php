<?php

namespace App\Models;

use App\Enums\PlacementSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'provider_id', 'ad_slot_id', 'slot', 'headline', 'body', 'image_path', 'click_url',
    'targeting', 'starts_on', 'ends_on', 'rate_cents', 'impressions', 'clicks', 'is_active',
])]
class Placement extends Model
{
    protected function casts(): array
    {
        return [
            'slot' => PlacementSlot::class,
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

    public function scopeActiveOn(Builder $query, ?string $date = null): Builder
    {
        $date ??= now('Africa/Johannesburg')->toDateString();

        return $query
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }
}
