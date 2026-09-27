<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use Database\Factories\ShooterEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category', 'label', 'notes'])]
class ShooterEquipment extends Model
{
    /** @use HasFactory<ShooterEquipmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => EquipmentCategory::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
