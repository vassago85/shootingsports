<?php

namespace App\Models;

use App\Enums\PlacementSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'provider_id', 'slot', 'targeting', 'starts_on', 'ends_on',
    'rate_cents', 'impressions', 'clicks', 'is_active',
])]
class Placement extends Model
{
    protected function casts(): array
    {
        return [
            'slot' => PlacementSlot::class,
            'targeting' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'rate_cents' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
