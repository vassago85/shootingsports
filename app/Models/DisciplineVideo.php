<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['discipline_id', 'title', 'url', 'video_id', 'sort_order'])]
class DisciplineVideo extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function thumbnailUrl(): ?string
    {
        return YouTube::thumbnail($this->video_id);
    }

    public function watchUrl(): ?string
    {
        return YouTube::watchUrl($this->video_id);
    }
}
