<?php

namespace App\Http\Controllers;

use App\Models\MagazineIssue;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MagazineIssueController extends Controller
{
    public function index(): View
    {
        $issues = MagazineIssue::query()
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->get();

        return view('public.magazines.index', [
            'issues' => $issues,
            'seo' => Seo::forMagazineIndex(),
            'jsonLd' => JsonLd::magazineIndex($issues->all()),
        ]);
    }

    public function show(Request $request, string $issue): View|RedirectResponse
    {
        $record = MagazineIssue::query()
            ->where('slug', $issue)
            ->firstOrFail();

        // Drafts are 404 to the public. A valid signed preview URL from
        // the Desk unlocks the page for the publisher without
        // publishing it. Previews always render noindex and skip the
        // read beacon.
        $preview = $request->hasValidSignature() && ! $record->isPublished();

        if (! $record->isPublished() && ! $preview) {
            abort(404);
        }

        // Account issues ask for one sign-in. Public issues stay open.
        // guest() stores this URL so sign-in returns the reader here.
        if ($record->requiresLogin() && ! $preview && $request->user() === null) {
            return redirect()->guest(route('login'));
        }

        return view('public.magazines.show', [
            'issue' => $record,
            'preview' => $preview,
            'seo' => Seo::forMagazineIssue($record, previewing: $preview),
            'jsonLd' => $preview ? null : JsonLd::magazineIssue($record),
        ]);
    }
}
