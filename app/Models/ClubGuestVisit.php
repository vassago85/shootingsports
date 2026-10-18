<?php

namespace App\Models;

use Database\Factories\ClubGuestVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest sign-in at a club. Keeps paper range-day logs honest
 * without turning every walk-in into a full member record.
 *
 * The "convert to application" record action spawns a new
 * {@see ClubMember} and stamps `converted_club_member_id` so the
 * paper trail is complete.
 */
#[Fillable([
    'organisation_id', 'name', 'email', 'phone',
    'visit_date', 'host_member_id', 'notes',
    'created_by', 'converted_club_member_id',
])]
class ClubGuestVisit extends Model
{
    /** @use HasFactory<ClubGuestVisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'visit_date' => 'immutable_date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function hostMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class, 'host_member_id');
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class, 'converted_club_member_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasBeenConverted(): bool
    {
        return $this->converted_club_member_id !== null;
    }
}
