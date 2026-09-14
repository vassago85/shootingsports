<?php

namespace App\Models;

use App\Enums\MediaRole;
use App\Enums\ModerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'disk', 'path', 'mime', 'bytes', 'width', 'height',
    'attachable_type', 'attachable_id', 'role', 'derivatives',
    'uploaded_by', 'moderation_status',
])]
class Media extends Model
{
    protected $table = 'media';

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'role' => MediaRole::class,
            'derivatives' => 'array',
            'moderation_status' => ModerationStatus::class,
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
