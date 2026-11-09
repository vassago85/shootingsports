<?php

namespace App\Services\Magazines;

use App\Support\Magazines\MagazineLinkMerger;
use App\Support\Magazines\PrintedUrlFinder;
use App\Support\Magazines\SafeLinkUrl;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\PDFObject;

/**
 * Reads link annotations and printed URLs out of a magazine PDF.
 * Coordinates are percentages of the PDF page, origin top-left.
 */
final class PdfMagazineLinkExtractor
{
    public function __construct(
        private readonly Parser $parser = new Parser,
        private readonly PrintedUrlFinder $urls = new PrintedUrlFinder,
        private readonly MagazineLinkMerger $merger = new MagazineLinkMerger,
    ) {}

    /**
     * @return list<array{
     *     page: int,
     *     pdf_page: int,
     *     x: float,
     *     y: float,
     *     w: float,
     *     h: float,
     *     url: string,
     *     label: string,
     *     source: string,
     *     advertiser: null
     * }>
     */
    public function extract(string $pdf): array
    {
        $document = $this->parser->parseContent($pdf);
        $links = [];
        $logical = 1;

        foreach ($document->getPages() as $index => $page) {
            $pdfPage = $index + 1;
            [$width, $height] = $this->pageSize($page);

            if ($width <= 0 || $height <= 0) {
                continue;
            }

            $spread = $width > $height * 1.15;
            $pageLinks = [
                ...$this->annotationLinks($page, $pdfPage, $width, $height),
                ...$this->textLinks($page, $pdfPage, $width, $height),
            ];

            foreach ($pageLinks as $link) {
                $center = $link['x'] + ($link['w'] / 2);
                $link['page'] = $spread && $center >= 50 ? $logical + 1 : $logical;
                $links[] = $link;
            }

            $logical += $spread ? 2 : 1;
        }

        /** @var list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}> $merged */
        $merged = $this->merger->merge($links);

        return $merged;
    }

    /**
     * Pages already measured by pdf.js: annotation boxes and text runs
     * are percentages of the page, origin top-left.
     *
     * @param  list<array{width?: float, height?: float, annotations?: list<array<string, mixed>>, items?: list<array<string, mixed>>}>  $pages
     * @return list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}>
     */
    public function fromMeasuredPages(array $pages): array
    {
        $links = [];
        $logical = 1;

        foreach ($pages as $index => $page) {
            $pdfPage = $index + 1;
            $width = (float) ($page['width'] ?? 0);
            $height = (float) ($page['height'] ?? 0);
            $spread = $width > $height * 1.15;
            $pageLinks = [];

            foreach ($page['annotations'] ?? [] as $annotation) {
                $url = SafeLinkUrl::make(is_string($annotation['url'] ?? null) ? $annotation['url'] : null);

                if ($url === null) {
                    continue;
                }

                $pageLinks[] = $this->link(
                    $pdfPage,
                    (float) ($annotation['x'] ?? 0),
                    (float) ($annotation['y'] ?? 0),
                    (float) ($annotation['w'] ?? 0),
                    (float) ($annotation['h'] ?? 0),
                    $url,
                    $url,
                    'annotation',
                );
            }

            foreach ($this->linksFromMeasuredItems($page['items'] ?? [], $pdfPage) as $link) {
                $pageLinks[] = $link;
            }

            foreach ($pageLinks as $link) {
                $center = $link['x'] + ($link['w'] / 2);
                $link['page'] = $spread && $center >= 50 ? $logical + 1 : $logical;
                $links[] = $link;
            }

            $logical += $spread ? 2 : 1;
        }

        /** @var list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}> $merged */
        $merged = $this->merger->merge($links);

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}>
     */
    private function linksFromMeasuredItems(array $items, int $pdfPage): array
    {
        $prepared = [];

        foreach ($items as $item) {
            $text = trim((string) ($item['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $prepared[] = [
                'text' => $text,
                'x' => (float) ($item['x'] ?? 0),
                'y' => (float) ($item['y'] ?? 0),
                'w' => max(1, (float) ($item['w'] ?? 1)),
                'h' => max(1, (float) ($item['h'] ?? 1)),
                'baseline' => (float) ($item['baseline'] ?? 0),
            ];
        }

        return $this->urlsOnLines($prepared, $pdfPage);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function pageSize(Page $page): array
    {
        $details = $page->getDetails();

        foreach (['MediaBox', 'CropBox'] as $key) {
            $box = $this->numbers($details[$key] ?? null);

            if (count($box) >= 4) {
                return [abs($box[2] - $box[0]), abs($box[3] - $box[1])];
            }
        }

        return [0.0, 0.0];
    }

    /**
     * @return list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}>
     */
    private function annotationLinks(Page $page, int $pdfPage, float $width, float $height): array
    {
        $links = [];

        foreach ($this->annotationObjects($page) as $annotation) {
            $details = $annotation->getDetails();
            $subtype = (string) ($details['Subtype'] ?? '');

            if (strcasecmp($subtype, 'Link') !== 0) {
                continue;
            }

            $url = SafeLinkUrl::make($this->uriFrom($annotation, $details));
            $box = $this->rectToPercent($details['Rect'] ?? null, $width, $height);

            if ($url === null || $box === null) {
                continue;
            }

            $links[] = [
                'page' => $pdfPage,
                'pdf_page' => $pdfPage,
                'x' => $box['x'],
                'y' => $box['y'],
                'w' => $box['w'],
                'h' => $box['h'],
                'url' => $url,
                'label' => $url,
                'source' => 'annotation',
                'advertiser' => null,
            ];
        }

        return $links;
    }

    /**
     * @return list<PDFObject>
     */
    private function annotationObjects(Page $page): array
    {
        $annots = $page->get('Annots');

        if (is_object($annots) && method_exists($annots, 'getContent')) {
            $content = $annots->getContent();

            if (is_array($content)) {
                return array_values(array_filter($content, fn (mixed $item): bool => $item instanceof PDFObject));
            }

            return $content instanceof PDFObject ? [$content] : [];
        }

        if ($annots instanceof PDFObject) {
            return [$annots];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function uriFrom(PDFObject $annotation, array $details): ?string
    {
        $action = $details['A'] ?? $details['Action'] ?? null;

        if (is_string($action) && $action !== '') {
            return $action;
        }

        if (is_array($action)) {
            $uri = $action['URI'] ?? $action['uri'] ?? null;

            return is_string($uri) ? $uri : null;
        }

        $object = $annotation->get('A');

        if ($object instanceof PDFObject) {
            $uri = $object->getDetails()['URI'] ?? null;

            return is_string($uri) ? $uri : null;
        }

        return null;
    }

    /**
     * @return list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}>
     */
    private function textLinks(Page $page, int $pdfPage, float $width, float $height): array
    {
        $items = [];

        foreach ($page->getDataTm() as $row) {
            if (! is_array($row) || count($row) < 2 || ! is_array($row[0]) || ! is_string($row[1])) {
                continue;
            }

            $text = trim($row[1]);

            if ($text === '') {
                continue;
            }

            $box = $this->textBox($row[0], $text, $width, $height);

            if ($box === null) {
                continue;
            }

            $items[] = ['text' => $text, ...$box];
        }

        return $this->urlsOnLines($items, $pdfPage);
    }

    /**
     * @param  list<array{text: string, x: float, y: float, w: float, h: float, baseline: float}>  $items
     * @return list<array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}>
     */
    private function urlsOnLines(array $items, int $pdfPage): array
    {
        usort($items, function (array $a, array $b): int {
            $y = $a['baseline'] <=> $b['baseline'];

            return $y !== 0 ? $y : ($a['x'] <=> $b['x']);
        });

        $lines = [];

        foreach ($items as $item) {
            $placed = false;

            foreach ($lines as &$line) {
                if (abs($line['baseline'] - $item['baseline']) <= 2) {
                    $line['items'][] = $item;
                    $placed = true;
                    break;
                }
            }

            unset($line);

            if (! $placed) {
                $lines[] = [
                    'baseline' => $item['baseline'],
                    'items' => [$item],
                ];
            }
        }

        $links = [];

        foreach ($lines as $line) {
            usort($line['items'], fn (array $a, array $b): int => $a['x'] <=> $b['x']);
            $string = '';
            $ranges = [];

            foreach ($line['items'] as $item) {
                if ($string !== '') {
                    $string .= ' ';
                }

                $start = strlen($string);
                $string .= $item['text'];
                $ranges[] = ['start' => $start, 'end' => strlen($string), 'box' => $item];
            }

            foreach ($this->urls->find($string) as $hit) {
                $hitEnd = $hit['start'] + $hit['length'];
                $boxes = [];

                foreach ($ranges as $range) {
                    if ($range['end'] <= $hit['start'] || $range['start'] >= $hitEnd) {
                        continue;
                    }

                    $boxes[] = $range['box'];
                }

                if ($boxes === []) {
                    continue;
                }

                $x = min(array_column($boxes, 'x'));
                $y = max(0, min(array_column($boxes, 'y')) - 0.6);
                $right = max(array_map(fn (array $box): float => $box['x'] + $box['w'], $boxes));
                $bottom = max(array_map(fn (array $box): float => $box['y'] + $box['h'], $boxes));

                $links[] = $this->link(
                    $pdfPage,
                    round($x, 2),
                    round($y, 2),
                    round(max(1, $right - $x), 2),
                    round(max(2.4, $bottom - $y), 2),
                    $hit['url'],
                    $hit['label'],
                    'text',
                );
            }
        }

        return $links;
    }

    /**
     * @return array{page: int, pdf_page: int, x: float, y: float, w: float, h: float, url: string, label: string, source: string, advertiser: null}
     */
    private function link(int $pdfPage, float $x, float $y, float $w, float $h, string $url, string $label, string $source): array
    {
        return [
            'page' => $pdfPage,
            'pdf_page' => $pdfPage,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'url' => $url,
            'label' => $label,
            'source' => $source,
            'advertiser' => null,
        ];
    }

    /**
     * @param  array<int, mixed>  $matrix
     * @return array{x: float, y: float, w: float, h: float, baseline: float}|null
     */
    private function textBox(array $matrix, string $text, float $width, float $height): ?array
    {
        if (count($matrix) < 6 || $width <= 0 || $height <= 0) {
            return null;
        }

        $x = (float) $matrix[4];
        $baseline = (float) $matrix[5];
        $fontSize = max(abs((float) ($matrix[0] ?? 0)), abs((float) ($matrix[3] ?? 0)), 8);
        $advance = max(1, mb_strlen($text)) * $fontSize * 0.52;
        $top = $height - $baseline - $fontSize;

        return [
            'x' => round(max(0, ($x / $width) * 100), 2),
            'y' => round(max(0, ($top / $height) * 100), 2),
            'w' => round(min(100, ($advance / $width) * 100), 2),
            'h' => round(min(100, max(1, ($fontSize * 1.25 / $height) * 100)), 2),
            'baseline' => $baseline,
        ];
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private function rectToPercent(mixed $rect, float $width, float $height): ?array
    {
        $numbers = $this->numbers($rect);

        if (count($numbers) < 4 || $width <= 0 || $height <= 0) {
            return null;
        }

        $left = min($numbers[0], $numbers[2]);
        $right = max($numbers[0], $numbers[2]);
        $bottom = min($numbers[1], $numbers[3]);
        $top = max($numbers[1], $numbers[3]);

        return [
            'x' => round(max(0, ($left / $width) * 100), 2),
            'y' => round(max(0, (($height - $top) / $height) * 100), 2),
            'w' => round(max(0.5, (($right - $left) / $width) * 100), 2),
            'h' => round(max(0.5, (($top - $bottom) / $height) * 100), 2),
        ];
    }

    /**
     * @return list<float>
     */
    private function numbers(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $numbers = [];

        foreach ($value as $item) {
            if (is_numeric($item)) {
                $numbers[] = (float) $item;
            }
        }

        return $numbers;
    }
}
