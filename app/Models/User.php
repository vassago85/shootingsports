<?php

namespace App\Models;

use App\Enums\DigestFrequency;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\Plan;
use App\Enums\Province;
use App\Enums\UserRole;
use App\Models\Concerns\HasPlan;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'calendar_slug', 'home_province', 'travel_radius_km',
    'digest_frequency', 'association_membership_number',
    'role', 'is_staff', 'is_match_director', 'is_media_partner',
    'md_requested_at', 'md_approved_at', 'md_rejected_at', 'md_rejection_reason',
    'supplier_requested_at', 'pending_business_name',
    'plan', 'plan_expires_at',
    'paystack_customer_code', 'paystack_subscription_code', 'paystack_authorization_code',
    'plan_billing_cycle', 'plan_cancelled_at',
    'emails_marketing_enabled', 'emails_match_alerts_enabled',
    'emails_weekly_digest_enabled', 'emails_product_updates_enabled',
    'emails_trial_nudges_enabled', 'emails_dedicated_reminders_enabled',
    'dedicated_body', 'dedicated_membership_number', 'dedicated_status_number',
    'dedicated_category', 'dedicated_required_count',
    'pro_trial_started_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPlan, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'home_province' => Province::class,
            'travel_radius_km' => 'integer',
            'digest_frequency' => DigestFrequency::class,
            'role' => UserRole::class,
            'is_staff' => 'boolean',
            'is_match_director' => 'boolean',
            'is_media_partner' => 'boolean',
            'md_requested_at' => 'datetime',
            'supplier_requested_at' => 'datetime',
            'md_approved_at' => 'datetime',
            'md_rejected_at' => 'datetime',
            'plan' => Plan::class,
            'plan_expires_at' => 'datetime',
            'plan_cancelled_at' => 'datetime',
            'emails_marketing_enabled' => 'boolean',
            'emails_match_alerts_enabled' => 'boolean',
            'emails_weekly_digest_enabled' => 'boolean',
            'emails_product_updates_enabled' => 'boolean',
            'emails_trial_nudges_enabled' => 'boolean',
            'emails_dedicated_reminders_enabled' => 'boolean',
            'unsubscribed_at' => 'datetime',
            'pro_trial_started_at' => 'datetime',
            'pro_trial_ending_notified_at' => 'datetime',
            'pro_trial_ended_notified_at' => 'datetime',
            'dedicated_required_count' => 'integer',
            'dedicated_last_reminded_cycle_end' => 'date',
        ];
    }

    /**
     * Every user gets a permanent, unguessable one-click unsubscribe
     * token the first time we persist them. New signups get one via
     * this observer; migrations back-fill existing users. Never rotated
     * (old email footers must keep working forever).
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->unsubscribe_token)) {
                $user->unsubscribe_token = Str::random(48);
            }
        });
    }

    /**
     * True until this person owns a supplier listing. Set when they tick
     * the supplier box at signup, or when they follow "List your business".
     * Stored on the user so confirming email on another device still
     * opens the listing form.
     */
    public function needsSupplierOnboarding(): bool
    {
        return $this->supplier_requested_at !== null && ! $this->isSupplier();
    }

    /**
     * A user is "pending MD review" when they hit /directors/register
     * but staff have not yet approved (or rejected) them. Panel access
     * still gates on is_match_director — this flag only drives UI copy
     * (the pending banner) and the Filament pending filter.
     */
    public function isMdPending(): bool
    {
        return $this->md_requested_at !== null
            && $this->is_match_director === false
            && $this->md_rejected_at === null;
    }

    /**
     * A user is "MD rejected" when staff explicitly said no. They can
     * re-apply — doing so clears md_rejected_at and refreshes
     * md_requested_at. Kept separate from isMdPending() so we can
     * show a distinct "we couldn't approve you" message.
     */
    public function isMdRejected(): bool
    {
        return $this->md_rejected_at !== null && $this->is_match_director === false;
    }

    /**
     * One-word status for tables + filters. Deliberately a string
     * (not an enum) — Filament TernaryFilter binds cleanly and there
     * is no callsite that switches on this value.
     */
    public function mdStatus(): string
    {
        return match (true) {
            $this->is_match_director === true => 'approved',
            $this->isMdPending() => 'pending',
            $this->isMdRejected() => 'rejected',
            default => 'none',
        };
    }

    // ----- Phase B: role + memberships ----------------------------------

    /**
     * Phase B shim. Reads the new `role` column first, falls back to
     * the old `is_staff` boolean so every existing call site keeps
     * working during the deploy window. Phase C drops the fallback.
     */
    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff
            || $this->role === UserRole::Admin
            || $this->is_staff === true;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** @return HasMany<ProviderMembership, $this> */
    public function providerMemberships(): HasMany
    {
        return $this->hasMany(ProviderMembership::class);
    }

    /** @return HasMany<VenueMembership, $this> */
    public function venueMemberships(): HasMany
    {
        return $this->hasMany(VenueMembership::class);
    }

    /**
     * Organisations this user actually manages (approved only). Phase
     * C scopes the desk to this collection; Phase D's event wizard
     * uses it to populate the "host club" picker.
     *
     * @return BelongsToMany<Organisation, $this>
     */
    public function managedOrganisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_user')
            ->using(OrganisationUser::class)
            ->withPivot(['role', 'status', 'requested_at', 'approved_at', 'granted_by', 'granted_at'])
            ->wherePivot('status', MembershipStatus::Approved->value)
            ->withTimestamps();
    }

    /** @return BelongsToMany<Provider, $this> */
    public function managedProviders(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'provider_user')
            ->withPivot(['role', 'status', 'requested_at', 'approved_at'])
            ->wherePivot('status', MembershipStatus::Approved->value)
            ->withTimestamps();
    }

    /**
     * Ranges this user actually manages (approved only). Range
     * ownership is a first-class capability stacked on the shooter
     * account, same shape as `managedProviders()` for shops.
     *
     * @return BelongsToMany<Venue, $this>
     */
    public function managedVenues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_user')
            ->withPivot(['role', 'status', 'requested_at', 'approved_at'])
            ->wherePivot('status', MembershipStatus::Approved->value)
            ->withTimestamps();
    }

    /**
     * True when the user holds at least one approved capability on
     * top of being a shooter — club, shop, range, or media partner.
     * Staff do not get desk access from this method alone; the panel
     * provider decides whether they can open `/desk` directly.
     */
    public function hasDeskAccess(): bool
    {
        if ($this->is_media_partner === true) {
            return true;
        }

        return $this->organisationMemberships()
            ->where('status', MembershipStatus::Approved->value)
            ->exists()
            || $this->providerMemberships()
                ->where('status', MembershipStatus::Approved->value)
                ->exists()
            || $this->venueMemberships()
                ->where('status', MembershipStatus::Approved->value)
                ->exists();
    }

    public function managesOrganisation(Organisation $organisation): bool
    {
        return $this->organisationMemberships()
            ->where('organisation_id', $organisation->id)
            ->where('status', MembershipStatus::Approved->value)
            ->exists();
    }

    public function managesProvider(Provider $provider): bool
    {
        return $this->providerMemberships()
            ->where('provider_id', $provider->id)
            ->where('status', MembershipStatus::Approved->value)
            ->exists();
    }

    public function managesVenue(Venue $venue): bool
    {
        return $this->venueMemberships()
            ->where('venue_id', $venue->id)
            ->where('status', MembershipStatus::Approved->value)
            ->exists();
    }

    /**
     * True when the user is a range owner or editor on at least one
     * venue. Mirrors {@see isSupplier()} for shops and backs the
     * Range capability card on the desk + the `range` entry in the
     * `/api/v1/me` capabilities payload.
     */
    public function isRangeManager(): bool
    {
        return $this->venueMemberships()
            ->where('status', MembershipStatus::Approved->value)
            ->exists();
    }

    /**
     * True when the user has an auto-renewing Paystack subscription that
     * is still charging. Cancelled-but-not-yet-expired subscriptions
     * return false here (use isCancelling() for that) — this is the
     * flag the "You are subscribed" section of the account page reads.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->paystack_subscription_code !== null
            && $this->plan_cancelled_at === null
            && $this->isPro();
    }

    /**
     * True when the user has hit "cancel" but their entitlement window
     * has not run out yet. UI should say "cancelled — Pro until X".
     */
    public function isCancelling(): bool
    {
        return $this->plan_cancelled_at !== null && $this->isPro();
    }

    /**
     * Human-readable subscription status for account pages and the
     * Filament admin. Never used for authorisation — that goes through
     * HasPlan::isPro() and the gates.
     */
    public function subscriptionStatusLabel(): string
    {
        return match (true) {
            $this->hasActiveSubscription() => 'Active. Renews automatically',
            $this->isCancelling() => 'Cancelled. Pro until '.$this->plan_expires_at?->format('j M Y'),
            $this->isOnTrial() => 'Trial. Pro until '.$this->plan_expires_at?->format('j M Y'),
            $this->isPro() => 'Active (comp / manual)',
            default => 'Free',
        };
    }

    /**
     * True while the user is inside their 30-day no-CC Pro trial. A
     * trial is identified by pro_trial_started_at being set AND them
     * having no Paystack subscription code — once they add a card the
     * subscription takes over and the trial concept no longer applies.
     * Expiry is handled naturally by HasPlan::isPro() checking
     * plan_expires_at, so this method returns false the moment the
     * trial window ends.
     */
    public function isOnTrial(): bool
    {
        return $this->pro_trial_started_at !== null
            && $this->paystack_subscription_code === null
            && $this->isPro();
    }

    /**
     * True if this user has ever started a Pro trial. Used by the
     * StartProTrial component to block re-triggering — each account
     * gets exactly one 30-day trial in its lifetime, whether they
     * completed it or converted mid-way.
     */
    public function hasHadTrial(): bool
    {
        return $this->pro_trial_started_at !== null;
    }

    /**
     * Whole days remaining on the trial. Returns 0 once expired
     * (never negative). Callers wanting a "1 day left" nudge should
     * check for exactly 1 or use daysRemaining < 4.
     */
    public function trialDaysRemaining(): int
    {
        if (! $this->isOnTrial() || $this->plan_expires_at === null) {
            return 0;
        }

        return max(0, (int) round(now()->diffInDays($this->plan_expires_at, false)));
    }

    /**
     * Route-model-binding helper for the public unsubscribe endpoints.
     * The token is looked up as-is; failed lookups return null so the
     * controller can 404 without leaking whether the address exists.
     */
    public static function findByUnsubscribeToken(string $token): ?self
    {
        if (trim($token) === '') {
            return null;
        }

        return static::query()->where('unsubscribe_token', $token)->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_staff,
            // Phase C — every signed-in user can open /desk. Directors
            // and industry get their home; shooters get the no-access
            // landing instead of a raw 403. Resource policies still
            // gate what they can actually edit.
            'desk' => true,
            default => false,
        };
    }

    /**
     * Phase C — which Desk home to render. Directors win when a user
     * holds both club and industry seats (same login, dual capability).
     */
    public function deskHomeMode(): string
    {
        return match (true) {
            $this->isDirectorDeskUser() => 'director',
            $this->isIndustryDeskUser() => 'industry',
            $this->is_media_partner => 'media',
            $this->is_staff => 'director',
            default => 'none',
        };
    }

    public function isDirectorDeskUser(): bool
    {
        return $this->is_match_director
            || $this->holdsAnyMemberManagingRole()
            || $this->organisationMemberships()
                ->where('status', MembershipStatus::Approved->value)
                ->exists();
    }

    public function isIndustryDeskUser(): bool
    {
        return $this->isSupplier()
            || $this->providerMemberships()
                ->where('status', MembershipStatus::Approved->value)
                ->exists();
    }

    /**
     * True when the user has an Admin or Secretary seat on any club.
     * Used both for Desk panel access and for `defaultRedirectPath()`
     * so a pure club secretary lands on `/desk` after login.
     */
    public function holdsAnyMemberManagingRole(): bool
    {
        return $this->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageMembers(),
            ))
            ->exists();
    }

    /**
     * Post-login landing URL. Staff drop into /admin, match directors
     * into /desk, plain shooters into their own calendar. Callers should
     * still respect any `intended` URL captured by the auth middleware —
     * this is only the default when no intended URL was set.
     */
    public function defaultRedirectPath(): string
    {
        return match (true) {
            $this->is_staff => '/admin',
            $this->is_match_director,
            $this->is_media_partner,
            $this->holdsAnyMemberManagingRole(),
            $this->isSupplier(),
            $this->hasDeskAccess() => '/desk',
            default => '/me/calendar',
        };
    }

    public function organisationMemberships(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class)
            ->using(OrganisationUser::class)
            ->withPivot(['role', 'granted_by', 'granted_at'])
            ->withTimestamps();
    }

    /**
     * @param  list<OrganisationUserRole|string>  $roles
     */
    public function holdsOrganisationRole(Organisation $organisation, array $roles = []): bool
    {
        $query = $this->organisationMemberships()
            ->where('organisation_id', $organisation->id);

        if ($roles !== []) {
            $values = array_map(
                fn (OrganisationUserRole|string $role): string => $role instanceof OrganisationUserRole ? $role->value : $role,
                $roles,
            );

            $query->whereIn('role', $values);
        }

        return $query->exists();
    }

    public function canManageOrganisationEvents(Organisation $organisation): bool
    {
        if ($this->is_staff) {
            return true;
        }

        return $this->holdsOrganisationRole($organisation, OrganisationUserRole::canManageEvents());
    }

    public function canManageClubMembers(Organisation $organisation): bool
    {
        if ($this->is_staff) {
            return true;
        }

        return $this->holdsOrganisationRole($organisation, OrganisationUserRole::canManageMembers());
    }

    public function clubMemberships(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function savedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'saved_events')->withTimestamps();
    }

    /**
     * @return BelongsToMany<MagazineIssue, $this>
     */
    public function savedMagazineIssues(): BelongsToMany
    {
        return $this->belongsToMany(MagazineIssue::class, 'saved_magazine_issues')->withTimestamps();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function ensureCalendarSlug(): string
    {
        if (filled($this->calendar_slug)) {
            return $this->calendar_slug;
        }

        $base = Str::slug($this->name) ?: 'shooter';
        $slug = $base;
        $i = 2;

        while (static::query()->where('calendar_slug', $slug)->whereKeyNot($this->id)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        $this->forceFill(['calendar_slug' => $slug])->save();

        return $slug;
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function attendedEvents(): HasMany
    {
        return $this->hasMany(AttendedEvent::class);
    }

    /**
     * True when the shooter has at least picked an accrediting body
     * AND holds Pro. The other dedicated-status fields are optional
     * — a shooter who's just NRAPA with no membership number yet
     * should still see the tracker and start logging activities.
     *
     * We intentionally gate the TRACKER (not the setup form) on Pro:
     * non-Pro shooters can prepare their fields while Pro is on hold
     * so the widget appears the moment Pro opens.
     */
    public function hasDedicatedTracker(): bool
    {
        $validBody = $this->dedicated_body !== null
            && array_key_exists((string) $this->dedicated_body, (array) config('dedicated.bodies', []));

        return $validBody && $this->isPro();
    }

    /**
     * Current (or past) activity cycle. The cycle END date comes from
     * config (`dedicated.cycle_end`, default 31 October) and the START
     * is the day AFTER the end, one year earlier (default 1 November).
     *
     * `$yearsBack` picks a past cycle for the printable-report "pick
     * previous cycle" selector: 0 = the cycle that ends on or after
     * today, 1 = the one before that, etc.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function dedicatedCycle(int $yearsBack = 0): array
    {
        $month = (int) config('dedicated.cycle_end.month', 10);
        $day = (int) config('dedicated.cycle_end.day', 31);
        $today = now()->startOfDay();

        // First guess: the cycle END in the current calendar year.
        $end = $today->copy()->setDate($today->year, $month, $day)->startOfDay();

        // If today is already past this year's end date, we're inside
        // the next cycle (which ends next calendar year). Roll end
        // forward one year so the "current" cycle is the one we're
        // actually in, not the one that just closed.
        if ($today->greaterThan($end)) {
            $end->addYear();
        }

        if ($yearsBack > 0) {
            $end->subYears($yearsBack);
        }

        // Start is one day after the previous cycle's end — i.e. one
        // year before this cycle's end, plus one day. For a 31 Oct
        // end that's 1 November of the previous year.
        $start = $end->copy()->subYear()->addDay();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Progress snapshot for the dedicated-status widget and the
     * printable report. Counts attendance log entries where the
     * linked event carries the "counts-toward-activity-requirements"
     * flag AND the event date falls inside the chosen cycle. Manual
     * log entries with no linked event never count — we can't verify
     * them.
     *
     * @return array{logged:int,required:int,remaining:int,days_to_renewal:int,window_start:Carbon,window_end:Carbon,body:string,body_label:string}
     */
    public function dedicatedProgress(int $yearsBack = 0): array
    {
        $required = max(
            (int) config('dedicated.minimum_required_count', 2),
            (int) ($this->dedicated_required_count ?? config('dedicated.default_required_count', 2)),
        );

        $cycle = $this->dedicatedCycle($yearsBack);
        $now = now()->startOfDay();

        $logged = $this->attendedEvents()
            ->whereBetween('event_date', [$cycle['start'], $cycle['end']])
            ->whereHas('event.flags', fn ($q) => $q->where('slug', 'counts-toward-activity-requirements'))
            ->count();

        $bodyCode = (string) ($this->dedicated_body ?? '');
        $bodies = (array) config('dedicated.bodies', []);

        return [
            'logged' => $logged,
            'required' => $required,
            'remaining' => max(0, $required - $logged),
            'days_to_renewal' => (int) $now->diffInDays($cycle['end'], false),
            'window_start' => $cycle['start'],
            'window_end' => $cycle['end'],
            'body' => $bodyCode,
            'body_label' => $bodies[$bodyCode] ?? $bodyCode,
        ];
    }

    public function eventEntries(): HasMany
    {
        return $this->hasMany(EventEntry::class);
    }

    public function eventResults(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ShooterEquipment::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    /**
     * Every Provider (industry listing) this user owns. Ownership is
     * established at supplier signup (see SupplierListing) and never
     * shared — a single Provider row belongs to exactly one user via
     * `claimed_by`. Staff can still edit anyone's listing from /admin.
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'claimed_by');
    }

    /**
     * Per-request cache for isSupplier(). Cleared automatically when
     * the model is refreshed (which is rare on the same request), and
     * short-circuited to null in the public setter so a fresh listing
     * mid-request re-runs the exists() check.
     */
    private ?bool $isSupplierCache = null;

    /**
     * True when the user has at least one supplier (industry) listing
     * they own. The public site's layout calls this on every request
     * (twice — desktop nav + mobile menu), so the result is memoised
     * per-instance to keep it to a single EXISTS query per page.
     *
     * If a caller creates a new provider under this user in the same
     * request they must call refreshSupplierState() (or reload the
     * model) — otherwise the cached false would hide the fresh listing
     * from the nav until the next request.
     */
    public function isSupplier(): bool
    {
        return $this->isSupplierCache ??= $this->providers()->exists();
    }

    public function refreshSupplierState(): void
    {
        $this->isSupplierCache = null;
    }
}
