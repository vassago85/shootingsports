<?php

namespace App\Models;

use Database\Factories\MagazineLinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'magazine_hotspot_id',
    'magazine_issue_id',
    'page',
    'organisation_id',
    'advertiser',
])]
class MagazineLinkClick extends Model
{
    /** @use HasFactory<MagazineLinkClickFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MagazineHotspot, $this>
     */
    public function hotspot(): BelongsTo
    {
        return $this->belongsTo(MagazineHotspot::class, 'magazine_hotspot_id');
    }

    /**
     * @return BelongsTo<MagazineIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class, 'magazine_issue_id');
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
