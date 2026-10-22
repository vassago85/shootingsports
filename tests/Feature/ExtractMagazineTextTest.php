<?php

use App\Jobs\ExtractMagazineText;
use App\Models\MagazineIssue;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('media');
    Process::preventStrayProcesses();
});

it('stores per-page text from pdftotext and keeps blank pages in place', function () {
    $issue = MagazineIssue::factory()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pages_text' => null,
    ]);

    Storage::disk('media')->put($issue->pdf_path, 'pdf-bytes');

    Process::fake(function (PendingProcess $process) {
        $command = $process->command;

        if (is_string($command) && (str_contains($command, 'pdftotext'))) {
            return Process::result(output: "/usr/bin/pdftotext\n");
        }

        if (is_array($command) && str_contains((string) ($command[0] ?? ''), 'pdftotext')) {
            return Process::result(output: "Cover line\f\fAdvert copy\f");
        }

        return Process::result(output: '', exitCode: 1);
    });

    (new ExtractMagazineText($issue->id))->handle();

    $fresh = $issue->fresh();

    expect($fresh->page_count)->toBe(3)
        ->and($fresh->pages_text)->toBe(['Cover line', '', 'Advert copy'])
        ->and($fresh->hasReadableText())->toBeTrue();
});

it('stores null when pdftotext returns only blank pages', function () {
    $issue = MagazineIssue::factory()->create([
        'pdf_path' => 'magazines/pdf/issue.pdf',
        'pages_text' => ['stale'],
    ]);

    Storage::disk('media')->put($issue->pdf_path, 'pdf-bytes');

    Process::fake(function (PendingProcess $process) {
        $command = $process->command;

        if (is_string($command) && str_contains($command, 'pdftotext')) {
            return Process::result(output: "/usr/bin/pdftotext\n");
        }

        if (is_array($command) && str_contains((string) ($command[0] ?? ''), 'pdftotext')) {
            return Process::result(output: "\f  \f");
        }

        return Process::result(output: '', exitCode: 1);
    });

    (new ExtractMagazineText($issue->id))->handle();

    $fresh = $issue->fresh();

    expect($fresh->pages_text)->toBeNull()
        ->and($fresh->hasReadableText())->toBeFalse();
});
