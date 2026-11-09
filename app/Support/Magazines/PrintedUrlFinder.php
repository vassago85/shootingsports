<?php

namespace App\Support\Magazines;

/**
 * Finds URLs and domains printed in magazine text. An email becomes a
 * mailto link and is not also treated as a website.
 */
final class PrintedUrlFinder
{
    private const string PATTERN = '/(?<email>[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})|(?<web>(?:https?:\/\/|www\.)[^\s<>"\'\)]+)|(?<domain>(?<![@\w.])(?:[a-z0-9](?:[a-z0-9\-]{0,62}[a-z0-9])?\.)+(?:co\.za|com|org|net|info|africa|co\.uk|io)\b)/iu';

    /**
     * @return list<array{url: string, label: string, start: int, length: int}>
     */
    public function find(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $found = [];
        $seen = [];

        foreach ($matches as $match) {
            $email = $match['email'][0] ?? '';
            $web = $match['web'][0] ?? '';
            $domain = $match['domain'][0] ?? '';
            $label = $email !== '' ? $email : ($web !== '' ? $web : $domain);
            $label = rtrim($label, ".,;:)]}>\"'");
            $offset = (int) ($match[0][1] ?? 0);

            if ($label === '') {
                continue;
            }

            $key = strtolower($label);

            if (isset($seen[$key])) {
                continue;
            }

            $url = SafeLinkUrl::make($email !== '' ? 'mailto:'.$label : $label);

            if ($url === null) {
                continue;
            }

            $seen[$key] = true;
            $found[] = [
                'url' => $url,
                'label' => $label,
                'start' => $offset,
                'length' => strlen($label),
            ];
        }

        return $found;
    }
}
