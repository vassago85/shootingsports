<?php

namespace Database\Seeders;

use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * On Target Africa issues. Reading stays on this site.
 * Issues 88 and 89 ship with the app and are copied onto the media disk.
 * Issues 90 and 91 are added from /admin.
 */
class OnTargetAfricaSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->where('is_staff', true)->orderBy('id')->value('id');

        if ($userId === null) {
            throw new RuntimeException('A staff account is required before On Target Africa issues can be saved.');
        }

        foreach ($this->issues() as $issue) {
            $slug = $issue['slug'];
            unset($issue['slug']);

            $pdfPath = 'magazines/pdf/'.$slug.'.pdf';
            $this->publishShippedPdf($slug, $pdfPath);
            if (Storage::disk('media')->exists($pdfPath)) {
                $issue['pdf_path'] = $pdfPath;
            }

            $existing = MagazineIssue::query()->where('slug', $slug)->first();

            if ($existing === null && $issue['issue_number'] === 87) {
                $existing = MagazineIssue::query()->where('slug', 'issue-87')->first();
            }

            if ($existing instanceof MagazineIssue) {
                $existing->fill($issue);
                if (filled($existing->pdf_path)) {
                    $existing->pdf_download_enabled = true;
                }
                $existing->save();

                continue;
            }

            MagazineIssue::query()->create($issue + [
                'slug' => $slug,
                'user_id' => $userId,
                'pdf_download_enabled' => filled($issue['pdf_path'] ?? null),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function issues(): array
    {
        return [
            $this->issue(87, '2026-08-01', 'August 2026'),
            $this->issue(88, '2026-09-01', 'September 2026'),
            $this->issue(89, '2026-10-01', 'October 2026'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(int $number, string $month, string $monthLabel): array
    {
        return [
            'slug' => 'on-target-africa-'.$number,
            'publication' => 'On Target Africa',
            'publisher_name' => 'On Target Africa',
            'title' => 'On Target Africa '.$number,
            'issue_label' => 'Issue '.$number.' · '.$monthLabel,
            'issue_number' => $number,
            'issue_month' => $month,
            'external_url' => null,
            'language' => 'en',
            'reader_access' => 'public',
            'published_at' => $month.' 08:00:00',
        ];
    }

    /**
     * Copy the PDF that ships with the app onto the media disk.
     * The production media directory is a mounted volume, so the file
     * inside the image is not visible until this copy runs.
     */
    private function publishShippedPdf(string $slug, string $pdfPath): void
    {
        $shipped = database_path('seeders/assets/magazines/'.$slug.'.pdf');

        if (! is_file($shipped)) {
            return;
        }

        $shippedSize = filesize($shipped);
        $disk = Storage::disk('media');
        $currentSize = $disk->exists($pdfPath) ? $disk->size($pdfPath) : null;

        if ($currentSize === $shippedSize) {
            return;
        }

        $stream = fopen($shipped, 'rb');

        if ($stream === false) {
            return;
        }

        $disk->put($pdfPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
