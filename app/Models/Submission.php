<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'type', 'payload', 'submitter_name', 'submitter_email', 'status',
    'merged_into_type', 'merged_into_id', 'moderator_id',
])]
class Submission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SubmissionType::class,
            'payload' => 'array',
            'status' => SubmissionStatus::class,
        ];
    }

    public function mergedInto(): MorphTo
    {
        return $this->morphTo();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
