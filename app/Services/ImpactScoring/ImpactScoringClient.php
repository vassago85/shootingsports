<?php

namespace App\Services\ImpactScoring;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Public Impact Scoring results API. No key. The same match links
 * SAPRF accepts: the match page, the results page, or the match number.
 */
class ImpactScoringClient
{
    public const API_BASE = 'https://app-api.impactscoring.net';

    public function __construct(
        private readonly ?PendingRequest $http = null,
    ) {}

    public function parseMatchId(string $input): int
    {
        $input = trim($input);

        if ($input === '') {
            throw new RuntimeException('Paste the Impact match link or the match number.');
        }

        if (ctype_digit($input)) {
            return (int) $input;
        }

        $parts = parse_url($input);
        $host = strtolower((string) (is_array($parts) ? ($parts['host'] ?? '') : ''));
        $host = (string) preg_replace('/^www\./', '', $host);
        $path = strtolower(rtrim((string) (is_array($parts) ? ($parts['path'] ?? '') : ''), '/'));
        parse_str((string) (is_array($parts) ? ($parts['query'] ?? '') : ''), $query);
        $id = $query['id'] ?? null;
        $isImpact = $host === '' || $host === 'impactscoring.net';
        $isMatchPage = in_array($path, ['/match/view', '/match/results'], true);

        if ($isImpact && $isMatchPage && is_scalar($id) && ctype_digit((string) $id)) {
            return (int) $id;
        }

        if ($isImpact && preg_match('#^/match/(?:view|results)/(\d+)$#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        throw new RuntimeException('Paste the Impact match link (/match/view?id=…) or the match number.');
    }

    /**
     * @return array{match: array<string, mixed>, shooters: list<array<string, mixed>>}
     */
    public function getLiveScores(int $impactMatchId): array
    {
        $response = $this->client()->get('/rest/website/getLiveScores', [
            'matchId' => $impactMatchId,
            'dividePoints' => 'false',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Impact did not return that match (HTTP {$response->status()}).");
        }

        $json = $response->json();

        if (! is_array($json) || (int) ($json['errorCode'] ?? -1) !== 0 || empty($json['payload'])) {
            throw new RuntimeException('Impact returned no scores for that match.');
        }

        $payload = $json['payload'];

        return [
            'match' => is_array($payload['match'] ?? null) ? $payload['match'] : [],
            'shooters' => array_values(is_array($payload['shooters'] ?? null) ? $payload['shooters'] : []),
        ];
    }

    private function client(): PendingRequest
    {
        return ($this->http ?? Http::baseUrl(self::API_BASE))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(2, 200);
    }
}
