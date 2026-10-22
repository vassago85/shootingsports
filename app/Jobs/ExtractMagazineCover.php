<?php

namespace App\Jobs;

use App\Models\MagazineIssue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Render page 1 of a magazine PDF into a listing-friendly JPEG cover
 * using poppler-utils' pdftoppm. Runs after upload only when the editor
 * did not provide their own cover, so a manually uploaded cover is
 * never clobbered.
 *
 * Requires the `pdftoppm` binary on PATH (installed via poppler-utils
 * in the production Docker image). Logs and no-ops cleanly if it is
 * missing on a workstation.
 */
class ExtractMagazineCover implements ShouldQueue
{
    use Queueable;

    /**
     * Match the ->imageResizeTargetWidth(1200) on the Filament form so
     * auto-extracted covers look identical to uploaded ones on the
     * public listings and social previews.
     */
    private const int TARGET_WIDTH = 1200;

    public function __construct(public int $magazineIssueId) {}

    public function handle(): void
    {
        $issue = MagazineIssue::query()->find($this->magazineIssueId);

        if ($issue === null || $issue->pdf_path === null) {
            return;
        }

        // Preserve an editor-uploaded cover. The create/edit pages only
        // dispatch this job when cover_path is null, but double-check
        // here so a race with a later upload cannot overwrite the file.
        if ($issue->cover_path !== null) {
            return;
        }

        $binary = $this->locateBinary();

        if ($binary === null) {
            Log::info('pdftoppm binary not available; skipping magazine cover extraction', [
                'issue_id' => $issue->id,
            ]);

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

        $tempDir = $this->makeTempDir();
        $inputPath = $tempDir.DIRECTORY_SEPARATOR.'input.pdf';
        $outputPrefix = $tempDir.DIRECTORY_SEPARATOR.'cover';

        try {
            file_put_contents($inputPath, (string) $disk->get($issue->pdf_path));

            $result = Process::timeout(60)->run([
                $binary,
                '-jpeg',
                '-r', '150',
                '-f', '1',
                '-l', '1',
                '-scale-to-x', (string) self::TARGET_WIDTH,
                '-scale-to-y', '-1',
                $inputPath,
                $outputPrefix,
            ]);

            if ($result->failed()) {
                Log::warning('pdftoppm failed for magazine cover', [
                    'issue_id' => $issue->id,
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                return;
            }

            $rendered = $this->renderedPath($outputPrefix);

            if ($rendered === null) {
                Log::warning('pdftoppm produced no cover file', [
                    'issue_id' => $issue->id,
                    'prefix' => $outputPrefix,
                ]);

                return;
            }

            $storedPath = sprintf(
                'magazines/covers/%s-%s.jpg',
                $issue->slug,
                now()->format('YmdHis'),
            );

            $disk->put($storedPath, (string) file_get_contents($rendered), 'public');

            $issue->forceFill(['cover_path' => $storedPath])->save();
        } catch (Throwable $e) {
            Log::warning('Magazine PDF cover extraction failed', [
                'issue_id' => $issue->id,
                'path' => $issue->pdf_path,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanup($tempDir);
        }
    }

    /**
     * Resolve `pdftoppm` on PATH. Returns null on dev workstations where
     * poppler-utils is not installed; the production Docker image ships
     * it via the Dockerfile apt-get block.
     */
    private function locateBinary(): ?string
    {
        $probe = PHP_OS_FAMILY === 'Windows' ? 'where pdftoppm' : 'command -v pdftoppm';
        $result = Process::run($probe);

        if ($result->failed()) {
            return null;
        }

        $path = trim(Str::before($result->output(), "\n"));

        return $path === '' ? null : $path;
    }

    private function makeTempDir(): string
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'magazine-cover-'.Str::random(12);
        mkdir($base, 0775, true);

        return $base;
    }

    /**
     * pdftoppm names its output `<prefix>-<page>.jpg` and pads the page
     * number with the width of the max page. Covering page 1 only, the
     * output is almost always `cover-1.jpg`, but glob to be safe.
     */
    private function renderedPath(string $prefix): ?string
    {
        $matches = glob($prefix.'-*.jpg');

        if ($matches === false || $matches === []) {
            return null;
        }

        return $matches[0];
    }

    private function cleanup(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach ((array) glob($directory.DIRECTORY_SEPARATOR.'*') as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }
}
