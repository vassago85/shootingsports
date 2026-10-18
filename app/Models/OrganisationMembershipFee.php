<?php

namespace App\Models;

use App\Enums\ClubMemberCategory;
use Database\Factories\OrganisationMembershipFeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price list for a club. One row per {@see ClubMemberCategory}; the
 * category string is stored (not the enum column itself) so a new
 * category value can be rolled out without a schema change.
 */
#[Fillable(['organisation_id', 'category', 'amount_cents', 'active'])]
class OrganisationMembershipFee extends Model
{
    /** @use HasFactory<OrganisationMembershipFeeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ClubMemberCategory::class,
            'amount_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
