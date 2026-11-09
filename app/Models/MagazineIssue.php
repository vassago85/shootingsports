<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Support\Magazines\MagazineLinkMerger;
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
    'user_id', 'publisher_organisation_id', 'publisher_name', 'publication', 'title', 'slug', 'issue_label', 'issue_number', 'issue_month', 'external_url', 'description',
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
            'page_images' => 'array',
            'page_count' => 'integer',
            'issue_number' => 'integer',
            'issue_month' => 'date',
            'pdf_download_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'publisher_organisation_id');
    }

    /**
     * Public byline. An organisation on the site wins over free text.
     * The uploading account is not the publisher.
     */
    public function publisherLabel(): ?string
    {
        $name = $this->publisher?->name ?: $this->publisher_name;

        return filled($name) ? $name : null;
    }

    /**
     * @return HasMany<MagazineRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MagazineRead::class);
    }

    /**
     * @return HasMany<MagazineHotspot, $this>
     */
    public function hotspots(): HasMany
    {
        return $this->hasMany(MagazineHotspot::class);
    }

    /**
     * @return HasMany<MagazineLinkClick, $this>
     */
    public function linkClicks(): HasMany
    {
        return $this->hasMany(MagazineLinkClick::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_magazine_issues')
            ->withPivot('last_page')
            ->withTimestamps();
    }

    protected function slugSource(): string
    {
        $publication = trim((string) $this->publication);

        if ($publication !== '' && filled($this->issue_number)) {
            return $publication.'-'.$this->issue_number;
        }

        return (string) ($this->title ?? '');
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

    /**
     * @return list<string>
     */
    public function pageImageUrls(): array
    {
        $paths = $this->page_images ?? [];

        if (! is_array($paths)) {
            return [];
        }

        $urls = [];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $urls[] = Storage::disk('media')->url($path);
            }
        }

        return $urls;
    }

    /**
     * Annotations, printed URLs, and manual hotspots. A manual box
     * replaces anything it overlaps.
     *
     * @return list<array{id: string, page: int, pdfPage: int, x: float, y: float, w: float, h: float, href: string, label: string}>
     */
    public function readerHotspots(): array
    {
        $rows = $this->relationLoaded('hotspots')
            ? $this->hotspots
            : $this->hotspots()->with('organisation')->get();

        $payload = $rows->map(fn (MagazineHotspot $hotspot): array => [
            'token' => $hotspot->token,
            'page' => $hotspot->page,
            'pdf_page' => $hotspot->pdf_page,
            'x' => $hotspot->x,
            'y' => $hotspot->y,
            'w' => $hotspot->w,
            'h' => $hotspot->h,
            'url' => $hotspot->url,
            'source' => $hotspot->source,
            'label' => $hotspot->label ?: $hotspot->displayName(),
        ])->all();

        return array_map(fn (array $link): array => [
            'id' => (string) $link['token'],
            'page' => (int) $link['page'],
            'pdfPage' => (int) $link['pdf_page'],
            'x' => (float) $link['x'],
            'y' => (float) $link['y'],
            'w' => (float) $link['w'],
            'h' => (float) $link['h'],
            'href' => route('magazines.go', $link['token']),
            'label' => (string) ($link['label'] ?: 'Advertiser link'),
        ], app(MagazineLinkMerger::class)->merge($payload));
    }

    /**
     * One row per advertiser, at the earliest page they appear.
     *
     * @return list<array{name: string, logo: ?string, page: int, listing_url: ?string}>
     */
    public function advertisers(): array
    {
        $rows = ($this->relationLoaded('hotspots')
            ? $this->hotspots
            : $this->hotspots()->with('organisation')->get())
            ->sortBy([
                ['page', 'asc'],
                ['id', 'asc'],
            ]);

        $groups = [];

        foreach ($rows as $hotspot) {
            $name = $hotspot->displayName();
            $key = $hotspot->organisation_id
                ? 'org:'.$hotspot->organisation_id
                : 'name:'.strtolower($name);

            if (isset($groups[$key])) {
                continue;
            }

            $listing = null;

            if ($hotspot->organisation !== null) {
                $listing = $hotspot->organisation->isFederationListing()
                    ? route('federations.show', $hotspot->organisation->slug)
                    : route('clubs.show', $hotspot->organisation->slug);
            }

            $groups[$key] = [
                'name' => $name,
                'logo' => $hotspot->organisation?->logoUrl(),
                'page' => $hotspot->page,
                'listing_url' => $listing,
            ];
        }

        return array_values($groups);
    }
}
