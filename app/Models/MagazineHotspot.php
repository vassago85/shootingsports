<?php

namespace App\Models;

use Database\Factories\MagazineHotspotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'magazine_issue_id',
    'page',
    'pdf_page',
    'x',
    'y',
    'w',
    'h',
    'url',
    'organisation_id',
    'advertiser',
    'label',
    'source',
    'token',
])]
class MagazineHotspot extends Model
{
    /** @use HasFactory<MagazineHotspotFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $hotspot): void {
            if (blank($hotspot->token)) {
                $hotspot->token = self::freshToken();
            }
        });
    }

    public static function freshToken(): string
    {
        do {
            $token = Str::lower(Str::random(16));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'pdf_page' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'w' => 'float',
            'h' => 'float',
        ];
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

    /**
     * @return HasMany<MagazineLinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(MagazineLinkClick::class);
    }

    public function displayName(): string
    {
        $name = $this->organisation?->name ?: $this->advertiser ?: $this->label;

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? (string) preg_replace('/^www\./', '', $host) : 'Advertiser';
    }
}
