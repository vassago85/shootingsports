<?php

namespace App\Jobs;

use App\Models\MagazineIssue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extract per-page text from a magazine PDF so:
 *   - readers can toggle a "Read as text" view the browser can translate
 *   - Google can index what the magazine actually says
 *   - the Desk dashboard knows the page count without a round-trip to pdf.js
 *
 * Fired after the Desk resource saves (upload or replace). Idempotent:
 * running it again on the same file produces the same result.
 */
class ExtractMagazineText implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $magazineIssueId) {}

    public function handle(): void
    {
        $issue = MagazineIssue::query()->find($this->magazineIssueId);

        if ($issue === null || $issue->pdf_path === null) {
            return;
        }

        $disk = Storage::disk('media');

        if (! $disk->exists($issue->pdf_path)) {
            Log::warning('Magazine PDF missing on media disk', [
                'issue_id' => $issue->id,
                'path' => $issue->pdf_path,
            ]);

            return;
        }

        try {
            $parser = new Parser;
            $document = $parser->parseContent((string) $disk->get($issue->pdf_path));

            $pages = [];

            foreach ($document->getPages() as $page) {
                $pages[] = $this->cleanWhitespace($page->getText());
            }

            $issue->forceFill([
                'page_count' => count($pages),
                'pages_text' => $pages,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Magazine PDF text extraction failed', [
                'issue_id' => $issue->id,
                'path' => $issue->pdf_path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanWhitespace(string $text): string
    {
        $normalised = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $normalised = (string) preg_replace("/\r\n|\r/", "\n", $normalised);
        $normalised = (string) preg_replace("/\n{3,}/", "\n\n", $normalised);

        return trim($normalised);
    }
}
