<?php

namespace App\Models;

use App\Enums\DigestFrequency;
use App\Enums\OrganisationUserRole;
use App\Enums\Province;
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

#[Fillable([
    'name', 'email', 'password', 'home_province', 'travel_radius_km',
    'digest_frequency', 'is_staff',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'home_province' => Province::class,
            'travel_radius_km' => 'integer',
            'digest_frequency' => DigestFrequency::class,
            'is_staff' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_staff;
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

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
