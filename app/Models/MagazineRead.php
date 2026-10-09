<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per unique reader of a {@see MagazineIssue}. Dedup key is
 * the opaque visitor hash (see MagazineReadController) so the same
 * person turning 60 pages still counts as one reader.
 */
#[Fillable([
    'magazine_issue_id', 'visitor_hash', 'max_page', 'pages_viewed',
    'first_seen_at', 'last_seen_at',
])]
class MagazineRead extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_page' => 'integer',
            'pages_viewed' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MagazineIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'magazine_issue_id');
    }
}
