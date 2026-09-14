<?php

namespace App\Models;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
            ->where('starts_at', '>=', now())
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

    public function publicUrl(): string
    {
        return route('matches.show', $this->slug);
    }
}
