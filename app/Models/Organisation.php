<?php

namespace App\Models;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasVerification;
use App\Support\Indexability;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'name', 'short_name', 'type', 'parent_id', 'province', 'town',
    'email', 'phone', 'website_url', 'facebook_url', 'description',
    'logo_path', 'accredited', 'visitors_welcome', 'status',
    'verification_state', 'last_verified_at', 'verification_token',
    'claimed_by', 'source',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasDisciplines, HasFactory, HasSlug, HasVerification, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
            'province' => Province::class,
            'accredited' => 'boolean',
            'visitors_welcome' => 'boolean',
            'status' => ListingStatus::class,
            'verification_state' => VerificationState::class,
            'last_verified_at' => 'immutable_datetime',
            'source' => ListingSource::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganisationUser::class)
            ->withPivot(['role', 'granted_by', 'granted_at'])
            ->withTimestamps();
    }

    public function hostedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'host_organisation_id');
    }

    public function federatedDisciplines(): HasMany
    {
        return $this->hasMany(Discipline::class, 'federation_organisation_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', ListingStatus::Published);
    }

    public function scopeIndexable($query)
    {
        return Indexability::organisations($query);
    }

    public function scopeClubs($query)
    {
        return $query->whereIn('type', [
            OrganisationType::Club,
            OrganisationType::Association,
            OrganisationType::Series,
        ]);
    }

    public function scopeFederations($query)
    {
        return $query->whereIn('type', [OrganisationType::Federation, OrganisationType::ProvincialBody]);
    }

    public function isFederationListing(): bool
    {
        return in_array($this->type, [OrganisationType::Federation, OrganisationType::ProvincialBody], true);
    }

    /**
     * Public URL of the uploaded logo, or null if none was set. Files are
     * stored on the "media" disk (bind-mounted to the extra HDD in production).
     */
    public function logoUrl(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        return Storage::disk('media')->url($this->logo_path);
    }

    public function locationLabel(): string
    {
        return collect([
            $this->town,
            $this->province?->getLabel(),
        ])->filter()->implode(', ') ?: '—';
    }

    public function schemaId(): string
    {
        $url = $this->isFederationListing()
            ? route('federations.show', $this->slug)
            : route('clubs.show', $this->slug);

        return $url.'#club';
    }

    public function initialsAvatarDataUri(): string
    {
        $initials = htmlspecialchars($this->avatarInitials(), ENT_XML1);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" role="img" aria-label="{$initials}">
  <rect width="64" height="64" rx="32" fill="#12181A"/>
  <circle cx="32" cy="32" r="30.5" fill="none" stroke="#D9AE52" stroke-width="1.5" opacity="0.55"/>
  <text x="32" y="39" text-anchor="middle" font-family="IBM Plex Sans, ui-sans-serif, sans-serif" font-size="20" font-weight="600" fill="#D9AE52">{$initials}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    public function avatarInitials(): string
    {
        $letters = collect(preg_split('/\s+/', trim((string) $this->name)) ?: [])
            ->filter()
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return $letters !== '' ? $letters : 'SS';
    }
}
