<?php

namespace App\Models;

use App\Enums\Division;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['discipline_id', 'division'])]
class DisciplineDivision extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'division' => Division::class,
        ];
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }
}
