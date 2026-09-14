<?php

namespace App\Models;

use App\Enums\OrganisationUserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['organisation_id', 'user_id', 'role', 'granted_by', 'granted_at'])]
class OrganisationUser extends Pivot
{
    protected $table = 'organisation_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => OrganisationUserRole::class,
            'granted_at' => 'immutable_datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
