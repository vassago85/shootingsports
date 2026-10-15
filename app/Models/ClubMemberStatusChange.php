<?php

namespace App\Models;

use App\Enums\ClubMemberStatus;
use Database\Factories\ClubMemberStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['club_member_id', 'from_status', 'to_status', 'changed_by', 'reason'])]
class ClubMemberStatusChange extends Model
{
    /** @use HasFactory<ClubMemberStatusChangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'from_status' => ClubMemberStatus::class,
            'to_status' => ClubMemberStatus::class,
        ];
    }

    public function clubMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
