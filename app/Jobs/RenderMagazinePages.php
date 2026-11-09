<?php

namespace App\Jobs;

use App\Models\MagazineIssue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renders each PDF page to a JPEG so the reader can show the issue
 * before pdf.js has fetched the file. Zoom still uses the PDF.
 */
class RenderMagazinePages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 900;

    public function __construct(public int $magazineIssueId) {}

    public function uniqueId(): string
    {
        return (string) $this->magazineIssueId;
    }

    public function handle(): void
    {
        $issue = MagazineIssue::query()->find($this->magazineIssueId);

        if ($issue === null || $issue->pdf_path === null) {
            return;
        }

        $binary = $this->locateBinary();

        if ($binary === null) {
            Log::info('pdftoppm binary not available; skipping magazine page render', [
                'issue_id' => $issue->id,
            ]);

            return;
        }

        $disk = Storage::disk('media');

        if (! $disk->exists($issue->pdf_path)) {
            return;
        }

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'magazine-pages-'.Str::random(12);
        mkdir($tempDir, 0775, true);
        $inputPath = $tempDir.DIRECTORY_SEPARATOR.'input.pdf';
        $prefix = $tempDir.DIRECTORY_SEPARATOR.'page';

        try {
            file_put_contents($inputPath, (string) $disk->get($issue->pdf_path));

            $result = Process::timeout(240)->run([
                $binary,
                '-jpeg',
                '-scale-to-x', '1600',
                '-scale-to-y', '-1',
                $inputPath,
                $prefix,
            ]);

            if ($result->failed()) {
                Log::warning('pdftoppm failed for magazine pages', [
                    'issue_id' => $issue->id,
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                ]);

                return;
            }

            $paths = [];

            foreach ((array) glob($prefix.'-*.jpg') as $file) {
                if (! is_string($file) || preg_match('/-(\d+)\.jpg$/', $file, $matches) !== 1) {
                    continue;
                }

                $page = (int) $matches[1];
                $stored = sprintf('magazines/pages/%d/%d.jpg', $issue->id, $page);
                $disk->put($stored, (string) file_get_contents($file), 'public');
                $paths[$page] = $stored;
            }

            if ($paths === []) {
                return;
            }

            ksort($paths);
            $issue->forceFill(['page_images' => array_values($paths)])->save();
        } catch (Throwable $e) {
            Log::warning('Magazine page render failed', [
                'issue_id' => $issue->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            foreach ((array) glob($tempDir.DIRECTORY_SEPARATOR.'*') as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($tempDir);
        }
    }

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
}
