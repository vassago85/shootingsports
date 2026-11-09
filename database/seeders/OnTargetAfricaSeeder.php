<?php

namespace Database\Seeders;

use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * On Target Africa issues. Each issue keeps its Yumpu URL. Issues 88 and 89
 * also open in the on-site reader when magazines/pdf/on-target-africa-{n}.pdf
 * is already on the media disk. Issues 90 and 91 are added from /admin.
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
            $this->issue(87, '2026-08-01', 'August 2026', 'https://www.yumpu.com/en/document/view/71271438/on-target-africa-87-august-2026-magazine-online'),
            $this->issue(88, '2026-09-01', 'September 2026', 'https://www.yumpu.com/en/document/view/71294262/ontargetafrica-88-september-2026-magazine'),
            $this->issue(89, '2026-10-01', 'October 2026', 'https://www.yumpu.com/en/document/view/71316791/on-target-africa-89-oct-2026-magazine'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(int $number, string $month, string $monthLabel, string $url): array
    {
        return [
            'slug' => 'on-target-africa-'.$number,
            'publication' => 'On Target Africa',
            'publisher_name' => 'On Target Africa',
            'title' => 'On Target Africa '.$number,
            'issue_label' => 'Issue '.$number.' · '.$monthLabel,
            'issue_number' => $number,
            'issue_month' => $month,
            'external_url' => $url,
            'language' => 'en',
            'reader_access' => 'public',
            'published_at' => $month.' 08:00:00',
        ];
    }
}
