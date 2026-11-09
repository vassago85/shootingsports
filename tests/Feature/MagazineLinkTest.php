<?php

use App\Models\MagazineHotspot;
use App\Models\MagazineIssue;
use App\Models\MagazineLinkClick;
use App\Models\SlugRedirect;
use App\Services\Magazines\PdfMagazineLinkExtractor;
use App\Support\Magazines\MagazineLinkMerger;
use App\Support\Magazines\PrintedUrlFinder;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

it('drops a printed url under an annotation and lets a manual hotspot win', function () {
    $merged = (new MagazineLinkMerger)->merge([
        box('annotation', 'https://annotated.example', 10, 10),
        box('text', 'https://covered.example', 12, 12),
        box('text', 'https://euroopticafrica.co.za', 70, 80),
        box('manual', 'https://manual.example', 10, 10),
    ]);

    expect(collect($merged)->pluck('url')->all())->toBe([
        'https://euroopticafrica.co.za',
        'https://manual.example',
    ]);
});

it('turns a printed domain into a link and an email into mailto', function () {
    $found = (new PrintedUrlFinder)->find('Write desk@euroopticafrica.co.za or visit euroopticafrica.co.za');

    expect(collect($found)->pluck('url')->all())->toBe([
        'mailto:desk@euroopticafrica.co.za',
        'https://euroopticafrica.co.za',
    ]);
});

it('extracts a link annotation and a printed url from a pdf', function () {
    $links = (new PdfMagazineLinkExtractor)->extract(sampleMagazinePdf());

    expect(collect($links)->pluck('url')->sort()->values()->all())->toBe([
        'https://euroopticafrica.co.za',
        'https://example.com/ad',
    ]);
});

it('logs a magazine click and redirects to the stored url', function () {
    $issue = MagazineIssue::factory()->published()->create();
    $hotspot = MagazineHotspot::factory()->create([
        'magazine_issue_id' => $issue->id,
        'url' => 'https://euroopticafrica.co.za',
        'page' => 2,
        'advertiser' => 'Euro Optic',
    ]);

    $this->get(route('magazines.go', $hotspot->token))
        ->assertStatus(302)
        ->assertRedirect('https://euroopticafrica.co.za');

    $click = MagazineLinkClick::query()->first();

    expect($click)->not->toBeNull()
        ->and($click->magazine_issue_id)->toBe($issue->id)
        ->and($click->magazine_hotspot_id)->toBe($hotspot->id)
        ->and($click->page)->toBe(2)
        ->and($click->advertiser)->toBe('Euro Optic');
});

it('does not redirect or log a click for a script url', function () {
    $hotspot = MagazineHotspot::factory()->create([
        'url' => 'javascript:alert(1)',
    ]);

    $this->get(route('magazines.go', $hotspot->token))->assertNotFound();

    expect(MagazineLinkClick::query()->count())->toBe(0);
});

it('redirects an old issue slug to the publication slug', function () {
    $issue = MagazineIssue::factory()->published()->create([
        'publication' => 'On Target Africa',
        'issue_number' => 87,
        'slug' => 'on-target-africa-87',
    ]);

    SlugRedirect::remember($issue, 'issue-87');

    $this->get('/magazines/issue-87')
        ->assertStatus(301)
        ->assertRedirect(route('magazines.show', 'on-target-africa-87'));
});

it('builds a magazine slug from the publication and issue number', function () {
    $issue = MagazineIssue::factory()->published()->make([
        'publication' => 'On Target Africa',
        'issue_number' => 87,
        'slug' => null,
    ]);

    $issue->save();

    expect($issue->slug)->toBe('on-target-africa-87');
});

/**
 * @return array{source: string, pdf_page: int, page: int, x: float, y: float, w: float, h: float, url: string}
 */
function box(string $source, string $url, float $x, float $y): array
{
    return [
        'source' => $source,
        'pdf_page' => 2,
        'page' => 2,
        'x' => $x,
        'y' => $y,
        'w' => 20,
        'h' => 8,
        'url' => $url,
    ];
}

function sampleMagazinePdf(): string
{
    $stream = "BT /F1 18 Tf 40 520 Td (euroopticafrica.co.za) Tj ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 600] /Contents 4 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [5 0 R] >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        '<< /Type /Annot /Subtype /Link /Rect [40 40 200 80] /A << /S /URI /URI (https://example.com/ad) >> >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    return $pdf;
}
