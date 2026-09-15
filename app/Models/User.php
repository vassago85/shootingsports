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
    'digest_frequency', 'is_staff', 'plan', 'plan_expires_at',
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
            'plan' => Plan::class,
            'plan_expires_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_staff,
            'desk' => true, // any registered user (match directors + staff)
            default => false,
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
