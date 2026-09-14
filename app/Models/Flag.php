<?php

namespace App\Models;

use App\Enums\FlagFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name', 'definition', 'family', 'sort_order', 'is_filterable'])]
class Flag extends Model
{
    protected function casts(): array
    {
        return [
            'family' => FlagFamily::class,
            'sort_order' => 'integer',
            'is_filterable' => 'boolean',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_flag');
    }
}
