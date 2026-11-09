<?php

namespace App\Http\Controllers;

use App\Models\MagazineHotspot;
use App\Models\MagazineLinkClick;
use App\Support\Magazines\SafeLinkUrl;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MagazineLinkController extends Controller
{
    /**
     * Logs an advertiser click, then leaves the site. The token is an
     * opaque id so the destination is never taken from the query string.
     */
    public function __invoke(string $hotspot): RedirectResponse
    {
        $record = MagazineHotspot::query()->where('token', $hotspot)->first();

        if (! $record instanceof MagazineHotspot) {
            throw new NotFoundHttpException;
        }

        $target = SafeLinkUrl::make($record->url);

        if ($target === null) {
            throw new NotFoundHttpException;
        }

        MagazineLinkClick::query()->create([
            'magazine_hotspot_id' => $record->id,
            'magazine_issue_id' => $record->magazine_issue_id,
            'page' => $record->page,
            'organisation_id' => $record->organisation_id,
            'advertiser' => $record->advertiser ?: $record->displayName(),
        ]);

        return redirect()->away($target, 302);
    }
}
