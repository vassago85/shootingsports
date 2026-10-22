<?php

use App\Jobs\ExtractMagazineCover;
use App\Models\MagazineIssue;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('media');
    Process::preventStrayProcesses();
});

it('skips when the issue has no PDF', function () {
    $issue = MagazineIssue::factory()->create(['pdf_path' => null, 'cover_path' => null]);

    (new ExtractMagazineCover($issue->id))->handle();

    expect($issue->fresh()->cover_path)->toBeNull();
});

it('preserves an existing cover_path and never shells out', function () {
    Process::fake();

    $issue = MagazineIssue::factory()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'cover_path' => 'magazines/covers/manual.jpg',
    ]);

    Storage::disk('media')->put($issue->pdf_path, 'pdf-bytes');

    (new ExtractMagazineCover($issue->id))->handle();

    expect($issue->fresh()->cover_path)->toBe('magazines/covers/manual.jpg');
    Process::assertNothingRan();
});

it('skips cleanly when the PDF is missing on the media disk', function () {
    $issue = MagazineIssue::factory()->create([
        'pdf_path' => 'magazines/pdf/missing.pdf',
        'cover_path' => null,
    ]);

    (new ExtractMagazineCover($issue->id))->handle();

    expect($issue->fresh()->cover_path)->toBeNull();
});

it('stores the rasterised cover and updates cover_path when pdftoppm succeeds', function () {
    $issue = MagazineIssue::factory()->create([
        'slug' => 'issue-99',
        'pdf_path' => 'magazines/pdf/issue-99.pdf',
        'cover_path' => null,
    ]);

    Storage::disk('media')->put($issue->pdf_path, 'pdf-bytes');

    // Fake both the `pdftoppm` probe and the render call. The job treats
    // "any file matching <prefix>-*.jpg" as the rendered output, so our
    // fake handler creates one on disk when the render invocation runs.
    Process::fake([
        'command -v pdftoppm' => Process::result(output: "/usr/bin/pdftoppm\n"),
        'where pdftoppm' => Process::result(output: "C:\\pdftoppm.exe\n"),
    ]);

    Process::fake(function (PendingProcess $process) {
        $command = $process->command;

        if (is_array($command) && str_contains((string) ($command[0] ?? ''), 'pdftoppm') && in_array('-jpeg', $command, true)) {
            $prefix = $command[array_key_last($command) - 1] === $command[count($command) - 2]
                ? $command[count($command) - 1]
                : end($command);

            file_put_contents($prefix.'-1.jpg', 'fake-jpeg-bytes');

            return Process::result(output: '', exitCode: 0);
        }

        if (is_string($command) && (str_contains($command, 'command -v pdftoppm') || str_contains($command, 'where pdftoppm'))) {
            return Process::result(output: "/usr/bin/pdftoppm\n");
        }

        return Process::result(output: '', exitCode: 0);
    });

    (new ExtractMagazineCover($issue->id))->handle();

    $fresh = $issue->fresh();

    expect($fresh->cover_path)
        ->not->toBeNull()
        ->and($fresh->cover_path)->toStartWith('magazines/covers/issue-99-');

    Storage::disk('media')->assertExists($fresh->cover_path);
    expect(Storage::disk('media')->get($fresh->cover_path))->toBe('fake-jpeg-bytes');
});

it('leaves cover_path null and logs when pdftoppm is not installed', function () {
    $issue = MagazineIssue::factory()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'cover_path' => null,
    ]);

    Storage::disk('media')->put($issue->pdf_path, 'pdf-bytes');

    Process::fake([
        'command -v pdftoppm' => Process::result(output: '', exitCode: 1),
        'where pdftoppm' => Process::result(output: '', exitCode: 1),
    ]);

    (new ExtractMagazineCover($issue->id))->handle();

    expect($issue->fresh()->cover_path)->toBeNull();
});
