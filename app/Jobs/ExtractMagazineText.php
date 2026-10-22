<?php

namespace App\Jobs;

use App\Models\MagazineIssue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $pages = $this->pagesFromPdftotext($disk->get($issue->pdf_path));

            if ($pages === null) {
                $pages = $this->pagesFromParser((string) $disk->get($issue->pdf_path));
            }

            $readable = array_filter($pages, static fn (string $page): bool => $page !== '');

            $issue->forceFill([
                'page_count' => count($pages),
                'pages_text' => $readable === [] ? null : array_values($pages),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Magazine PDF text extraction failed', [
                'issue_id' => $issue->id,
                'path' => $issue->pdf_path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Poppler's pdftotext reads the magazine fonts that the PHP parser
     * misses. Returns null when the binary is not installed so the
     * caller can fall back. Pages are split on the form-feed pdftotext
     * writes between pages.
     *
     * @return list<string>|null
     */
    private function pagesFromPdftotext(string $pdf): ?array
    {
        $binary = $this->locatePdftotext();

        if ($binary === null) {
            return null;
        }

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'magazine-text-'.Str::random(12);
        mkdir($tempDir, 0775, true);
        $inputPath = $tempDir.DIRECTORY_SEPARATOR.'input.pdf';

        try {
            file_put_contents($inputPath, $pdf);

            $result = Process::timeout(120)->run([
                $binary,
                '-layout',
                '-enc', 'UTF-8',
                $inputPath,
                '-',
            ]);

            if ($result->failed()) {
                Log::warning('pdftotext failed for magazine text', [
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                return null;
            }

            $chunks = explode("\f", $result->output());

            if ($chunks !== [] && trim((string) end($chunks)) === '') {
                array_pop($chunks);
            }

            return array_map($this->cleanWhitespace(...), $chunks);
        } finally {
            if (is_file($inputPath)) {
                @unlink($inputPath);
            }

            @rmdir($tempDir);
        }
    }

    /**
     * @return list<string>
     */
    private function pagesFromParser(string $pdf): array
    {
        $parser = new Parser;
        $document = $parser->parseContent($pdf);
        $pages = [];

        foreach ($document->getPages() as $page) {
            $pages[] = $this->cleanWhitespace($page->getText());
        }

        return $pages;
    }

    private function locatePdftotext(): ?string
    {
        $probe = PHP_OS_FAMILY === 'Windows' ? 'where pdftotext' : 'command -v pdftotext';
        $result = Process::run($probe);

        if ($result->failed()) {
            return null;
        }

        $path = trim(Str::before($result->output(), "\n"));

        return $path === '' ? null : $path;
    }

    private function cleanWhitespace(string $text): string
    {
        $normalised = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $normalised = (string) preg_replace("/\r\n|\r/", "\n", $normalised);
        $normalised = (string) preg_replace("/\n{3,}/", "\n\n", $normalised);

        return trim($normalised);
    }
}
