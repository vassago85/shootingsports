<?php

namespace App\Models;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use App\Support\Indexability;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'title', 'host_organisation_id', 'venue_id', 'starts_at', 'ends_at',
    'all_day', 'level', 'status', 'confirmed_at', 'original_starts_at',
    'entry_fee_cents', 'member_fee_cents', 'entry_url', 'capacity',
    'entries_taken', 'round_count', 'target_count', 'stage_count',
    'results_url', 'banner_media_id', 'banner_path', 'description', 'created_by',
    'source', 'last_verified_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasDisciplines, HasFactory, HasSlug, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'level' => EventLevel::class,
            'status' => EventStatus::class,
            'confirmed_at' => 'immutable_datetime',
            'original_starts_at' => 'immutable_datetime',
            'entry_fee_cents' => 'integer',
            'member_fee_cents' => 'integer',
            'capacity' => 'integer',
            'entries_taken' => 'integer',
            'round_count' => 'integer',
            'target_count' => 'integer',
            'stage_count' => 'integer',
            'source' => ListingSource::class,
            'last_verified_at' => 'immutable_datetime',
        ];
    }

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function hostOrganisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'host_organisation_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Multi-venue pivot. `events.venue_id` still holds the primary
     * venue for back-compat with importers, filters, JSON-LD, and the
     * map — it must always match the pivot row with the lowest
     * `sort_order`. Use `syncVenues()` to keep both sides consistent.
     */
    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'event_venue')
            ->withPivot(['day_label', 'starts_on', 'sort_order'])
            ->withTimestamps()
            ->orderBy('event_venue.sort_order');
    }

    /**
     * Every venue attached to this match, in display order. Falls
     * back to the single `venue_id` column when the pivot is empty —
     * the dual-read that lets legacy single-venue events keep working
     * while multi-venue rolls out.
     *
     * @return Collection<int, Venue>
     */
    public function allVenues(): Collection
    {
        $this->loadMissing('venues');

        if ($this->venues->isNotEmpty()) {
            return $this->venues->values();
        }

        return $this->venue ? collect([$this->venue]) : collect();
    }

    /**
     * Replace the multi-venue pivot with the supplied rows and keep
     * `events.venue_id` in sync with the primary (lowest sort_order).
     *
     * @param  list<array{venue_id: int, day_label?: string|null, starts_on?: string|\DateTimeInterface|null}>  $rows
     */
    public function syncVenues(array $rows): void
    {
        $payload = [];
        $order = 0;

        foreach ($rows as $row) {
            $venueId = (int) ($row['venue_id'] ?? 0);

            if ($venueId <= 0 || isset($payload[$venueId])) {
                continue;
            }

            $payload[$venueId] = [
                'day_label' => $row['day_label'] ?? null,
                'starts_on' => $row['starts_on'] ?? null,
                'sort_order' => $order++,
            ];
        }

        $this->venues()->sync($payload);

        // Primary venue_id mirrors the first pivot row. Empty pivot
        // clears venue_id — the caller can still set it directly for
        // legacy single-venue writes that never touch the pivot.
        $primary = array_key_first($payload);
        $this->forceFill(['venue_id' => $primary])->save();
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_media_id');
    }

    /**
     * Return the public URL of the event banner, whether it was uploaded
     * directly via the Filament form (banner_path on the "media" disk) or
     * attached through the legacy Media model. Direct uploads win.
     */
    public function bannerUrl(): ?string
    {
        if (filled($this->banner_path)) {
            return Storage::disk('media')->url($this->banner_path);
        }

        return $this->banner?->url();
    }

    /**
     * Image for public cards: the match banner, or the host club / series /
     * federation logo when the event has not uploaded one yet.
     */
    public function coverImageUrl(): ?string
    {
        return $this->bannerUrl()
            ?? $this->hostOrganisation?->logoUrl()
            ?? $this->hostOrganisation?->parent?->logoUrl();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function flags(): BelongsToMany
    {
        return $this->belongsToMany(Flag::class, 'event_flag');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_events')->withTimestamps();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'attachable_id')
            ->where('attachable_type', $this->getMorphClass());
    }

    public function isProvisional(): bool
    {
        return $this->status === EventStatus::Planned;
    }

    public function confirm(): void
    {
        $this->forceFill([
            'status' => EventStatus::Confirmed,
            'confirmed_at' => now(),
        ])->save();
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $window): void {
                $window->where('starts_at', '>=', now())
                    ->orWhere(function (Builder $stillRunning): void {
                        $stillRunning->whereNotNull('ends_at')
                            ->where('ends_at', '>=', now());
                    });
            })
            ->whereNotIn('status', [
                EventStatus::Draft,
                EventStatus::Cancelled,
                EventStatus::Completed,
            ])
            // Draft clubs/series stay off the public calendar until staff publish them.
            ->whereHas(
                'hostOrganisation',
                fn (Builder $org) => $org->where('status', ListingStatus::Published),
            );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', '!=', EventStatus::Draft);
    }

    public function scopeIndexable(Builder $query): Builder
    {
        return Indexability::events($query);
    }

    public function scopeConfirmedOnly(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EventStatus::Confirmed,
            EventStatus::EntriesOpen,
            EventStatus::Full,
        ]);
    }

    public function locationLabel(): string
    {
        // Multi-venue: show a count instead of the primary alone so
        // the card doesn't misrepresent a two-range match. Detail
        // page still enumerates each range in full.
        $venueCount = $this->relationLoaded('venues') ? $this->venues->count() : 0;

        if ($venueCount > 1) {
            $primary = $this->venues->first();

            return ($primary?->town ?: $primary?->name).' + '.($venueCount - 1).' more';
        }

        if ($this->venue) {
            return collect([
                $this->venue->town ?: $this->venue->name,
                $this->venue->province?->code(),
            ])->filter()->implode(' · ');
        }

        $host = $this->hostOrganisation;

        if ($host?->town || $host?->province) {
            return collect([$host->town, $host->province?->code()])->filter()->implode(' · ');
        }

        return 'Venue TBC';
    }

    /**
     * Location for public pages. Null when the match has no venue
     * and no host town, so templates can omit the line entirely.
     */
    public function listedLocation(): ?string
    {
        $label = $this->locationLabel();

        return $label === 'Venue TBC' || $label === '' ? null : $label;
    }

    /**
     * Host name for public pages. Null when neither a club nor a
     * range is on the match.
     */
    public function listedHost(): ?string
    {
        $name = $this->hostOrganisation?->name ?? $this->venue?->name;

        return filled($name) ? $name : null;
    }

    /**
     * "Who is putting on this match" for cards / meta lines. Prefers
     * the host organisation, falls back to the venue when a range
     * operator hosts the match themselves (no external club), and
     * gives up to a neutral placeholder when neither exists.
     */
    public function hostDisplayName(): string
    {
        return $this->hostOrganisation?->name
            ?? $this->venue?->name
            ?? 'Independent match';
    }

    public function publicUrl(): string
    {
        return route('matches.show', $this->slug);
    }

    public function schemaId(): string
    {
        return $this->publicUrl().'#event';
    }
}
