<?php

namespace App\Jobs;

use App\Models\MagazineHotspot;
use App\Models\MagazineIssue;
use App\Services\Magazines\PdfMagazineLinkExtractor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores link annotations and printed URLs for a magazine so the
 * reader does not parse the PDF in the browser on every visit.
 * Hand-drawn hotspots are left in place.
 */
class ExtractMagazineLinks implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $magazineIssueId) {}

    public function uniqueId(): string
    {
        return (string) $this->magazineIssueId;
    }

    public function handle(PdfMagazineLinkExtractor $extractor): void
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

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'magazine-links-'.Str::random(12);
        mkdir($tempDir, 0775, true);
        $inputPath = $tempDir.DIRECTORY_SEPARATOR.'input.pdf';

        try {
            file_put_contents($inputPath, (string) $disk->get($issue->pdf_path));
            $links = $this->linksFromNode($inputPath, $extractor) ?? $extractor->extract((string) file_get_contents($inputPath));
        } catch (Throwable $e) {
            Log::warning('Magazine link extraction failed', [
                'issue_id' => $issue->id,
                'path' => $issue->pdf_path,
                'error' => $e->getMessage(),
            ]);

            return;
        } finally {
            if (is_file($inputPath)) {
                @unlink($inputPath);
            }

            @rmdir($tempDir);
        }

        $existing = $issue->hotspots()->whereIn('source', ['annotation', 'text'])->get();
        $kept = [];

        foreach ($links as $link) {
            $match = $existing->first(function (MagazineHotspot $hotspot) use ($link, $kept): bool {
                return ! in_array($hotspot->id, $kept, true)
                    && $hotspot->source === $link['source']
                    && $hotspot->pdf_page === $link['pdf_page']
                    && $hotspot->url === $link['url'];
            });

            if ($match instanceof MagazineHotspot) {
                $match->fill([
                    'page' => $link['page'],
                    'x' => $link['x'],
                    'y' => $link['y'],
                    'w' => $link['w'],
                    'h' => $link['h'],
                    'label' => $link['label'],
                ])->save();
                $kept[] = $match->id;

                continue;
            }

            $created = $issue->hotspots()->create([
                'page' => $link['page'],
                'pdf_page' => $link['pdf_page'],
                'x' => $link['x'],
                'y' => $link['y'],
                'w' => $link['w'],
                'h' => $link['h'],
                'url' => $link['url'],
                'label' => $link['label'],
                'source' => $link['source'],
                'advertiser' => null,
            ]);
            $kept[] = $created->id;
        }

        $issue->hotspots()
            ->whereIn('source', ['annotation', 'text'])
            ->whereNotIn('id', $kept)
            ->delete();
    }

    /**
     * pdf.js reads annotations and text positions without loading the
     * whole magazine into the PHP parser, which exhausts memory on a
     * full issue.
     *
     * @return list<array<string, mixed>>|null
     */
    private function linksFromNode(string $path, PdfMagazineLinkExtractor $extractor): ?array
    {
        $node = PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
        $result = Process::timeout(180)->run([
            $node,
            base_path('resources/js/extract-magazine-links.mjs'),
            $path,
        ]);

        if ($result->failed()) {
            Log::info('Node magazine link extraction unavailable', [
                'exit_code' => $result->exitCode(),
                'stderr' => Str::limit($result->errorOutput(), 500),
            ]);

            return null;
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded) || ! is_array($decoded['pages'] ?? null)) {
            return null;
        }

        return $extractor->fromMeasuredPages($decoded['pages']);
    }
}
