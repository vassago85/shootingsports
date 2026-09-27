<?php

namespace App\Http\Controllers;

use App\Enums\EventKind;
use App\Models\Article;
use App\Models\Discipline;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __invoke(Request $request): View
    {
        $discipline = $request->filled('discipline')
            ? Discipline::query()->where('slug', $request->string('discipline')->toString())->where('is_published', true)->first()
            : null;
        $kind = EventKind::tryFrom($request->string('kind')->toString());

        $articles = Article::query()
            ->published()
            ->with(['author', 'disciplines'])
            ->when($discipline, fn ($query) => $query->whereHas('disciplines', fn ($inner) => $inner->whereKey($discipline->id)))
            ->when($kind, fn ($query) => $query->whereJsonContains('event_kinds', $kind->value))
            ->orderByDesc('published_at')
            ->limit(40)
            ->get();

        return view('public.feed.index', [
            'articles' => $articles,
            'disciplines' => Discipline::query()->where('is_published', true)->orderBy('name')->get(),
            'kinds' => EventKind::cases(),
            'discipline' => $discipline,
            'kind' => $kind,
        ]);
    }
}
