<?php

namespace App\Models;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Concerns\HasDisciplines;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasVerification;
use App\Services\Platform\AgencyAgreement;
use App\Support\Indexability;
use Carbon\CarbonImmutable;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'name', 'short_name', 'type', 'parent_id', 'province', 'town',
    'email', 'phone', 'website_url', 'facebook_url', 'description',
    'logo_path', 'accredited', 'visitors_welcome',
    'membership_enabled', 'accepts_applications',
    'membership_year_end_month', 'membership_year_end_day',
    'renewal_reminder_days', 'membership_online_payments', 'membership_eft_instructions',
    'membership_custom_fields',
    'status',
    'verification_state', 'last_verified_at', 'verification_token',
    'claimed_by', 'source', 'trusted',
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
            'membership_enabled' => 'boolean',
            'accepts_applications' => 'boolean',
            'membership_year_end_month' => 'integer',
            'membership_year_end_day' => 'integer',
            'renewal_reminder_days' => 'array',
            'membership_online_payments' => 'boolean',
            'membership_custom_fields' => 'array',
            'status' => ListingStatus::class,
            'verification_state' => VerificationState::class,
            'last_verified_at' => 'immutable_datetime',
            'source' => ListingSource::class,
            'trusted' => 'boolean',
            'active_member_count' => 'integer',
            'tier_grace_ends_at' => 'date',
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
            ->withPivot(['role', 'status', 'granted_by', 'granted_at', 'requested_at', 'approved_at'])
            ->withTimestamps();
    }

    /**
     * Approved managers only — the people the Desk should scope
     * organisation views to. Phase C uses this for the "who gets to
     * edit this club" check.
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('status', MembershipStatus::Approved->value);
    }

    public function hostedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'host_organisation_id');
    }

    public function clubMembers(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function platformSubscription(): HasOne
    {
        return $this->hasOne(PlatformSubscription::class);
    }

    public function membershipFees(): HasMany
    {
        return $this->hasMany(OrganisationMembershipFee::class);
    }

    public function membershipInvoices(): HasMany
    {
        return $this->hasMany(MembershipInvoice::class);
    }

    public function guestVisits(): HasMany
    {
        return $this->hasMany(ClubGuestVisit::class);
    }

    public function agencyAgreementAcceptances(): HasMany
    {
        return $this->hasMany(AgencyAgreementAcceptance::class);
    }

    public function hasSignedCurrentAgencyAgreement(): bool
    {
        return $this->agencyAgreementAcceptances()
            ->where('version', AgencyAgreement::currentVersion())
            ->exists();
    }

    /**
     * Both toggles must be on — a club can keep its register private
     * while still being published publicly.
     */
    public function acceptsMembershipApplications(): bool
    {
        return $this->membership_enabled && $this->accepts_applications;
    }

    /**
     * Membership year-end for a given reference date. Returns an
     * immutable date on the configured month/day in the same year, or
     * — when the reference is already past that date — in the next
     * year. Defaults to 31 Dec of the current/next calendar year.
     */
    public function membershipYearEndFor(Carbon|CarbonImmutable|null $reference = null): CarbonImmutable
    {
        $ref = $reference !== null
            ? CarbonImmutable::parse($reference->toDateString())
            : CarbonImmutable::today();

        $month = max(1, min(12, (int) ($this->membership_year_end_month ?: 12)));
        $day = max(1, min(31, (int) ($this->membership_year_end_day ?: 31)));

        $candidate = CarbonImmutable::create($ref->year, $month, min($day, (int) $ref->copy()->day($day)->endOfMonth()->day));

        if ($candidate === null) {
            $candidate = CarbonImmutable::create($ref->year, 12, 31);
        }

        if ($candidate->lt($ref)) {
            $candidate = $candidate->addYear();
        }

        return $candidate;
    }

    /**
     * Ordered descending list of reminder day-offsets before expiry
     * (e.g. [30, 14, 7]). Returns the default when unset or empty, so
     * the scheduled command always has something to iterate over.
     *
     * @return list<int>
     */
    public function renewalReminderDaysOrDefault(): array
    {
        $raw = $this->renewal_reminder_days ?? [];

        $days = collect(is_array($raw) ? $raw : [])
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $days !== [] ? $days : [30, 14, 7];
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
