<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMagazineDownloadLinkRequest;
use App\Mail\MagazineDownloadLink;
use App\Models\MagazineIssue;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MagazineIssueController extends Controller
{
    public function index(): View
    {
        $issues = MagazineIssue::query()
            ->published()
            ->with(['author', 'publisher'])
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
            ->with('publisher')
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

    public function emailDownloadLink(StoreMagazineDownloadLinkRequest $request, string $issue): RedirectResponse
    {
        $record = $this->downloadableIssue($issue);
        $email = strtolower($request->validated('email'));

        foreach (['magazine-download:'.$request->ip(), 'magazine-download:'.$email] as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return back()
                    ->withInput()
                    ->withErrors(['email' => 'Too many download links from this network. Please try again later.']);
            }
        }

        foreach (['magazine-download:'.$request->ip(), 'magazine-download:'.$email] as $key) {
            RateLimiter::hit($key, 60 * 15);
        }

        $url = URL::temporarySignedRoute(
            'magazines.download',
            now()->addDays(MagazineDownloadLink::LINK_DAYS),
            ['issue' => $record->slug],
        );

        Mail::to($email)->queue((new MagazineDownloadLink($record, $url))->afterCommit());

        return back()->with('download_link_sent', true);
    }

    public function download(string $issue): StreamedResponse
    {
        $record = $this->downloadableIssue($issue);

        return Storage::disk('media')->download(
            (string) $record->pdf_path,
            Str::slug($record->title).'.pdf',
        );
    }

    private function downloadableIssue(string $slug): MagazineIssue
    {
        $record = MagazineIssue::query()->where('slug', $slug)->firstOrFail();

        abort_unless($record->isPublished() && $record->allowsPdfDownload(), 404);
        abort_unless(
            $record->pdf_path !== null && Storage::disk('media')->exists($record->pdf_path),
            404,
        );

        return $record;
    }
}
