<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\MagazineIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A full-issue magazine (default publication: "Targets") that readers
 * open as a page-flip viewer and also browse as searchable text.
 */
#[Fillable([
    'user_id', 'publication', 'title', 'slug', 'issue_label', 'description',
    'language', 'pdf_path', 'pdf_download_enabled', 'cover_path', 'page_count',
    'pages_text', 'published_at', 'reader_access',
])]
class MagazineIssue extends Model
{
    /** @use HasFactory<MagazineIssueFactory> */
    use HasFactory, HasSlug;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pages_text' => 'array',
            'page_count' => 'integer',
            'pdf_download_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<MagazineRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MagazineRead::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_magazine_issues')->withTimestamps();
    }

    /**
     * @param  Builder<MagazineIssue>  $query
     * @return Builder<MagazineIssue>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /**
     * Anyone can open a public issue. Account issues ask for one sign-in.
     */
    public function requiresLogin(): bool
    {
        return $this->reader_access === 'account';
    }

    /**
     * Public URL for the PDF binary (used as the download fallback and
     * fetched by pdf.js on the show page).
     */
    public function pdfUrl(): ?string
    {
        return $this->pdf_path !== null
            ? Storage::disk('media')->url($this->pdf_path)
            : null;
    }

    /**
     * Readers may save the file only when the issue offers a download
     * and a PDF is actually stored.
     */
    public function allowsPdfDownload(): bool
    {
        return $this->pdf_download_enabled && $this->pdfUrl() !== null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path !== null
            ? Storage::disk('media')->url($this->cover_path)
            : null;
    }

    /**
     * Count of unique readers (one row per visitor hash).
     */
    public function readersCount(): int
    {
        return (int) $this->reads()->count();
    }

    /**
     * Average pages viewed per reader. Zero when there are no reads yet.
     */
    public function averagePagesRead(): float
    {
        $value = $this->reads()->avg('pages_viewed');

        return $value === null ? 0.0 : round((float) $value, 1);
    }

    /**
     * @return list<string>
     */
    public function pagesText(): array
    {
        $pages = $this->pages_text ?? [];

        return array_values(array_map(static fn (mixed $page): string => (string) $page, $pages));
    }

    /**
     * True when at least one page yielded real text. Designed magazine
     * PDFs sometimes come back as a list of empty strings; that is not
     * a text version worth showing.
     */
    public function hasReadableText(): bool
    {
        foreach ($this->pagesText() as $page) {
            if (trim($page) !== '') {
                return true;
            }
        }

        return false;
    }
}
