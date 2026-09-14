<?php

namespace App\Models;

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasVerification;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug', 'name', 'province', 'town', 'metro', 'lat', 'lng', 'address',
    'max_distance_m', 'bay_count', 'access', 'day_fee_cents', 'facilities',
    'notes', 'tier', 'status', 'verification_state', 'last_verified_at',
    'verification_token', 'claimed_by', 'source',
])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasDisciplines, HasFactory, HasSlug, HasVerification, SoftDeletes;

    protected function casts(): array
    {
        return [
            'province' => Province::class,
            'metro' => GautengMetro::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'max_distance_m' => 'integer',
            'bay_count' => 'integer',
            'access' => VenueAccess::class,
            'day_fee_cents' => 'integer',
            'facilities' => 'array',
            'tier' => ProviderTier::class,
            'status' => ListingStatus::class,
            'verification_state' => VerificationState::class,
            'last_verified_at' => 'immutable_datetime',
            'source' => ListingSource::class,
        ];
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', ListingStatus::Published);
    }

    public function scopeOrderByTier($query)
    {
        return $query
            ->orderByRaw("case tier when 'featured' then 0 when 'verified' then 1 else 2 end")
            ->orderBy('name');
    }
}
