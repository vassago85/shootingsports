<?php

namespace App\Models;

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasVerification;
use App\Support\Indexability;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'slug', 'name', 'category', 'services', 'province', 'town', 'metro', 'lat', 'lng',
    'email', 'phone', 'website_url', 'description', 'tier', 'status',
    'verification_state', 'last_verified_at', 'verification_token',
    'claimed_by', 'source',
])]
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasDisciplines, HasFactory, HasSlug, HasVerification, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ProviderCategory::class,
            'services' => 'array',
            'province' => Province::class,
            'metro' => GautengMetro::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'tier' => ProviderTier::class,
            'status' => ListingStatus::class,
            'verification_state' => VerificationState::class,
            'last_verified_at' => 'immutable_datetime',
            'source' => ListingSource::class,
        ];
    }

    /**
     * Extra services this supplier offers beyond the primary `category`.
     *
     * Returns a de-duplicated collection of ProviderCategory enums,
     * excluding the primary category so callers can render the primary
     * separately (as a headline) without repeating it in the services
     * list. Unknown / stale enum values in the DB are silently dropped.
     *
     * @return Collection<int, ProviderCategory>
     */
    public function serviceCategories(): Collection
    {
        $values = collect((array) $this->services)
            ->map(fn ($value): ?ProviderCategory => is_string($value) ? ProviderCategory::tryFrom($value) : null)
            ->filter()
            ->unique();

        if ($this->category !== null) {
            $values = $values->reject(fn (ProviderCategory $c): bool => $c === $this->category);
        }

        return $values->values();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', ListingStatus::Published);
    }

    /**
     * Public directory. A distributor account exists so the business can
     * advertise. It is not a listing visitors can open.
     */
    public function scopeListed($query)
    {
        return $query->where(function ($query): void {
            $query->whereNull('category')
                ->orWhereNot('category', ProviderCategory::Distributor);
        });
    }

    public function scopeIndexable($query)
    {
        return Indexability::providers($query);
    }

    public function scopeOrderByTier($query)
    {
        return $query
            ->orderByRaw("case tier when 'featured' then 0 when 'verified' then 1 else 2 end")
            ->orderBy('name');
    }

    /**
     * UX audit #12 — Directory-populated gate.
     *
     * Returns true when the public Industry directory has enough real
     * listings to be worth showing. An empty "10 categories, 0 listed"
     * grid is a worse ad for a paid listing product than no directory
     * at all, so nav / homepage sections / hero stats all check this
     * before rendering.
     *
     * The threshold is deliberately conservative (5) — bump it once
     * we have more real listings if the grid still reads thin. Cached
     * because it's called from the layout on every request.
     */
    public static function isDirectoryPopulated(int $threshold = 5): bool
    {
        return Cache::remember(
            'provider.directory-populated.'.$threshold,
            300,
            fn (): bool => static::query()->published()->listed()->count() >= $threshold,
        );
    }
}
