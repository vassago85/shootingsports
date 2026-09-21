<?php

namespace App\Models;

use App\Enums\DigestFrequency;
use App\Enums\OrganisationUserRole;
use App\Enums\Plan;
use App\Enums\Province;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'calendar_slug', 'home_province', 'travel_radius_km',
    'digest_frequency', 'association_membership_number',
    'is_staff', 'is_match_director',
    'md_requested_at', 'md_approved_at', 'md_rejected_at', 'md_rejection_reason',
    'plan', 'plan_expires_at',
    'paystack_customer_code', 'paystack_subscription_code', 'paystack_authorization_code',
    'plan_billing_cycle', 'plan_cancelled_at',
    'emails_marketing_enabled', 'emails_match_alerts_enabled',
    'emails_weekly_digest_enabled', 'emails_product_updates_enabled',
    'emails_trial_nudges_enabled',
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
            'is_staff' => 'boolean',
            'is_match_director' => 'boolean',
            'md_requested_at' => 'datetime',
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
            'unsubscribed_at' => 'datetime',
            'pro_trial_started_at' => 'datetime',
            'pro_trial_ending_notified_at' => 'datetime',
            'pro_trial_ended_notified_at' => 'datetime',
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
            $this->hasActiveSubscription() => 'Active — renews automatically',
            $this->isCancelling() => 'Cancelled — Pro until '.$this->plan_expires_at?->format('j M Y'),
            $this->isOnTrial() => 'Trial — Pro until '.$this->plan_expires_at?->format('j M Y'),
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
            // Staff can access desk unconditionally (staff > MD > shooter).
            // Everyone else must have opted into the MD signup path.
            'desk' => $this->is_staff || $this->is_match_director,
            default => false,
        };
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
            $this->is_match_director => '/desk',
            default => '/my-calendar',
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

    public function savedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'saved_events')->withTimestamps();
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
     * True when the user has at least one supplier (industry) listing
     * they own. Cheap enough to inline in Livewire mounts; do not
     * cache — a fresh signup creates their first listing mid-request
     * and expects this flag to flip on the next call.
     */
    public function isSupplier(): bool
    {
        return $this->providers()->exists();
    }
}
