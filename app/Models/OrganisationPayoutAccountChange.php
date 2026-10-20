<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Database\Factories\OrganisationPayoutAccountChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organisation_payout_account_id', 'user_id',
    'old_last_four', 'new_last_four',
    'old_bank_name', 'new_bank_name',
    'old_account_holder', 'new_account_holder',
    'old_status', 'new_status',
])]
class OrganisationPayoutAccountChange extends Model
{
    /** @use HasFactory<OrganisationPayoutAccountChangeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'old_status' => PayoutStatus::class,
            'new_status' => PayoutStatus::class,
        ];
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(OrganisationPayoutAccount::class, 'organisation_payout_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
