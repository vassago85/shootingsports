<?php

namespace App\Http\Controllers;

use App\Models\MagazineIssue;
use App\Models\MagazineRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Beacon endpoint for the flipbook. Records one row per unique reader
 * per issue, updating max_page and pages_viewed as they turn pages.
 *
 * Drafts, signed previews and bots never increment the counter — the
 * dashboard is a readership number for the publisher, not a traffic
 * log.
 */
class MagazineReadController extends Controller
{
    public function store(Request $request, string $issue): JsonResponse
    {
        $record = MagazineIssue::query()
            ->where('slug', $issue)
            ->firstOrFail();

        if (! $record->isPublished()) {
            return response()->json(['tracked' => false, 'reason' => 'draft']);
        }

        if ($record->requiresLogin() && $request->user() === null) {
            return response()->json(['tracked' => false, 'reason' => 'login']);
        }

        if ($request->hasValidSignature()) {
            // Signed preview URLs reach this endpoint from the Desk's
            // own flipbook test — ignore so review traffic does not
            // pollute the readership numbers.
            return response()->json(['tracked' => false, 'reason' => 'preview']);
        }

        if ($this->looksLikeBot($request)) {
            return response()->json(['tracked' => false, 'reason' => 'bot']);
        }

        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        $hash = $this->visitorHash($request, $record->id);
        $now = now();

        $row = MagazineRead::query()
            ->where('magazine_issue_id', $record->id)
            ->where('visitor_hash', $hash)
            ->first();

        if ($row === null) {
            MagazineRead::query()->create([
                'magazine_issue_id' => $record->id,
                'visitor_hash' => $hash,
                'max_page' => $page,
                'pages_viewed' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        } else {
            $row->forceFill([
                'max_page' => max($row->max_page, $page),
                'pages_viewed' => $row->pages_viewed + 1,
                'last_seen_at' => $now,
            ])->save();
        }

        return response()->json(['tracked' => true]);
    }

    private function visitorHash(Request $request, int $issueId): string
    {
        return hash('sha256', implode('|', [
            (string) config('app.key'),
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) $issueId,
        ]));
    }

    private function looksLikeBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        if ($agent === '') {
            return true;
        }

        return (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|pingdom|lighthouse|headless/i', $agent);
    }
}
