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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'name', 'category', 'services', 'province', 'town', 'metro', 'lat', 'lng',
    'email', 'phone', 'website_url', 'logo_path', 'tagline', 'description', 'tier', 'status',
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

    /**
     * Whether this listing should appear on a category page: as the
     * primary category, or as one of the extra services.
     */
    public function offers(ProviderCategory $category): bool
    {
        if ($this->category === $category) {
            return true;
        }

        return $this->serviceCategories()->contains(
            fn (ProviderCategory $service): bool => $service === $category,
        );
    }

    /**
     * Published listings whose primary category or extra services match.
     */
    public function scopeOffering($query, ProviderCategory $category)
    {
        return $query->where(function ($query) use ($category): void {
            $query->where('category', $category)
                ->orWhereJsonContains('services', $category->value);
        });
    }

    /**
     * Public URL of the uploaded logo, or null when none is set.
     * Files live on the "media" disk, same as organisation logos.
     */
    public function logoUrl(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        return Storage::disk('media')->url($this->logo_path);
    }

    /**
     * Store a replacement logo and drop the previous file.
     */
    public function attachLogo(UploadedFile $logo): bool
    {
        $stored = $logo->store('provider-logos', 'media');

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        if (filled($this->logo_path) && $this->logo_path !== $stored) {
            Storage::disk('media')->delete($this->logo_path);
        }

        $this->logo_path = $stored;

        return true;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * One line for directory rows. The tagline wins; otherwise a clip of the longer description.
     */
    public function cardSummary(): ?string
    {
        if (filled($this->tagline)) {
            return $this->tagline;
        }

        $description = trim((string) $this->description);

        if ($description === '') {
            return null;
        }

        $collapsed = preg_replace('/\s+/', ' ', $description);

        return Str::limit(is_string($collapsed) ? $collapsed : $description, 140);
    }

    /**
     * Primary category, then any extra services, for the public profile grid.
     *
     * @return Collection<int, ProviderCategory>
     */
    public function offeredCategories(): Collection
    {
        return collect([$this->category])
            ->merge($this->serviceCategories())
            ->filter()
            ->unique()
            ->values();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function sponsoredEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_provider')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('event_provider.sort_order');
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
