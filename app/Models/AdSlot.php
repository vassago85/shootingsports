<?php

namespace App\Models;

use App\Enums\AdPage;
use App\Enums\PlacementSlot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'page', 'slot', 'name', 'price_cents', 'notes', 'is_active',
])]
class AdSlot extends Model
{
    protected function casts(): array
    {
        return [
            'page' => AdPage::class,
            'slot' => PlacementSlot::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function priceRands(): string
    {
        return 'R '.number_format($this->price_cents / 100, 0, '.', ' ');
    }
}
