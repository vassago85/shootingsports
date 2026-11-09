<?php

namespace App\Support\Magazines;

/**
 * Combines PDF annotations, printed URLs, and hand-drawn hotspots.
 * A manual box wins wherever it overlaps. A printed URL is kept only
 * when no annotation already covers that spot.
 */
final class MagazineLinkMerger
{
    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array<string, mixed>>
     */
    public function merge(array $links): array
    {
        $manual = $this->ofSource($links, 'manual');
        $annotations = $this->ofSource($links, 'annotation');
        $printed = $this->ofSource($links, 'text');

        $printed = array_values(array_filter(
            $printed,
            fn (array $link): bool => ! $this->coveredBy($link, [...$annotations, ...$manual]),
        ));

        $annotations = array_values(array_filter(
            $annotations,
            fn (array $link): bool => ! $this->coveredBy($link, $manual),
        ));

        return [...$annotations, ...$printed, ...$manual];
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array<string, mixed>>
     */
    private function ofSource(array $links, string $source): array
    {
        return array_values(array_filter(
            $links,
            fn (array $link): bool => ($link['source'] ?? '') === $source,
        ));
    }

    /**
     * @param  array<string, mixed>  $link
     * @param  list<array<string, mixed>>  $others
     */
    private function coveredBy(array $link, array $others): bool
    {
        foreach ($others as $other) {
            if ((int) ($link['pdf_page'] ?? $link['page'] ?? 0) !== (int) ($other['pdf_page'] ?? $other['page'] ?? 0)) {
                continue;
            }

            if ($this->overlaps($link, $other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function overlaps(array $a, array $b): bool
    {
        $ax2 = (float) $a['x'] + (float) $a['w'];
        $ay2 = (float) $a['y'] + (float) $a['h'];
        $bx2 = (float) $b['x'] + (float) $b['w'];
        $by2 = (float) $b['y'] + (float) $b['h'];

        return (float) $a['x'] < $bx2
            && $ax2 > (float) $b['x']
            && (float) $a['y'] < $by2
            && $ay2 > (float) $b['y'];
    }
}
