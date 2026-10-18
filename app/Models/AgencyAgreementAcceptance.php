<?php

namespace App\Models;

use Database\Factories\AgencyAgreementAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organisation_id',
    'user_id',
    'version',
    'ip_address',
])]
class AgencyAgreementAcceptance extends Model
{
    /** @use HasFactory<AgencyAgreementAcceptanceFactory> */
    use HasFactory;

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
