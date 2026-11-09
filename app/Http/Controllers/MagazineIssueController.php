<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMagazineDownloadLinkRequest;
use App\Mail\MagazineDownloadLink;
use App\Models\MagazineIssue;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MagazineIssueController extends Controller
{
    public function index(Request $request): View
    {
        $published = MagazineIssue::query()
            ->published()
            ->with(['author', 'publisher'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $publication = $request->string('publication')->trim()->toString();
        $year = $request->integer('year');

        $issues = $published
            ->when($publication !== '', fn ($rows) => $rows->where('publication', $publication))
            ->when($year > 0, function ($rows) use ($year) {
                return $rows->filter(function (MagazineIssue $issue) use ($year): bool {
                    $issueYear = $issue->issue_month?->year ?? $issue->published_at?->year;

                    return $issueYear === $year;
                });
            })
            ->values();

        $continue = [];
        $user = $request->user();

        if ($user instanceof User) {
            foreach ($user->savedMagazineIssues()->wherePivot('last_page', '>', 1)->get() as $saved) {
                $continue[$saved->id] = (int) $saved->pivot->last_page;
            }
        }

        $years = $published
            ->map(fn (MagazineIssue $issue): ?int => $issue->issue_month?->year ?? $issue->published_at?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('public.magazines.index', [
            'issues' => $issues,
            'publications' => $published->pluck('publication')->filter()->unique()->sort()->values(),
            'years' => $years,
            'publication' => $publication,
            'year' => $year > 0 ? $year : null,
            'continue' => $continue,
            'seo' => Seo::forMagazineIndex(),
            'jsonLd' => JsonLd::magazineIndex($issues->all()),
        ]);
    }

    public function show(Request $request, string $issue): View|RedirectResponse
    {
        $resolved = $this->publishedOrRedirect($issue);

        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        $record = $resolved->load(['publisher', 'hotspots.organisation']);

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

        $resumePage = null;
        $user = $request->user();

        if ($user instanceof User && ! $request->filled('page')) {
            $saved = $user->savedMagazineIssues()->where('magazine_issues.id', $record->id)->first();
            $last = (int) ($saved?->pivot?->last_page ?? 0);
            $resumePage = $last > 1 ? $last : null;
        }

        return view('public.magazines.show', [
            'issue' => $record,
            'preview' => $preview,
            'resumePage' => $resumePage,
            'seo' => Seo::forMagazineIssue($record, previewing: $preview),
            'jsonLd' => $preview ? null : JsonLd::magazineIssue($record),
        ]);
    }

    public function progress(Request $request, string $issue): Response
    {
        $record = MagazineIssue::query()->where('slug', $issue)->firstOrFail();
        abort_unless($record->isPublished(), 404);

        $page = $request->integer('page');
        $limit = max(1, (int) ($record->page_count ?? 2000));
        abort_unless($page >= 1 && $page <= max($limit, 2000), 422);

        $user = $request->user();

        if ($user instanceof User) {
            $saved = $user->savedMagazineIssues()->where('magazine_issues.id', $record->id)->exists();

            if ($saved) {
                $user->savedMagazineIssues()->updateExistingPivot($record->id, ['last_page' => $page]);
            }
        }

        return response()->noContent();
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

    private function publishedOrRedirect(string $slug): MagazineIssue|RedirectResponse
    {
        $record = MagazineIssue::query()->where('slug', $slug)->first();

        if ($record instanceof MagazineIssue) {
            return $record;
        }

        $target = SlugRedirect::findTarget(MagazineIssue::class, $slug);

        if ($target instanceof MagazineIssue) {
            $url = route('magazines.show', $target->slug);
            $query = request()->getQueryString();

            return redirect()->to($query ? $url.'?'.$query : $url, 301);
        }

        abort(404);
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
