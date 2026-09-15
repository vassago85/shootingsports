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
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'email', 'password', 'calendar_slug', 'home_province', 'travel_radius_km',
    'digest_frequency', 'is_staff', 'is_match_director', 'plan', 'plan_expires_at',
    'paystack_customer_code', 'paystack_subscription_code', 'paystack_authorization_code',
    'plan_billing_cycle', 'plan_cancelled_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPlan, Notifiable;

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
            'plan' => Plan::class,
            'plan_expires_at' => 'datetime',
            'plan_cancelled_at' => 'datetime',
        ];
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
            $this->isPro() => 'Active (comp / manual)',
            default => 'Free',
        };
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

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
