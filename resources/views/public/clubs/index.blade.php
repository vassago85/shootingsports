<x-layouts.public :title="$seoTitle" :description="$seoDescription" :json-ld="$jsonLd">
    <main id="main" class="dir-page">
        <div class="wrap">
            <x-dir-hero page="clubs" kicker="Clubs" title="Find a shooting club">
                Find your club. Membership clubs, associations, and branded match series.
            </x-dir-hero>

            <form class="dir-filters" method="get" action="{{ route('clubs.index') }}">
                @if ($division)
                    <input type="hidden" name="division" value="{{ $division->value }}">
                @endif
                <label class="dir-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Club name">
                </label>
                <label class="dir-field">
                    <span>Sport</span>
                    <select name="discipline">
                        <option value="">Any sport</option>
                        @foreach ($disciplines as $sport)
                            <option value="{{ $sport->slug }}" @selected($selectedDiscipline?->is($sport))>{{ $sport->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dir-field">
                    <span>Province</span>
                    <select name="province">
                        <option value="">Any province</option>
                        @foreach ($provinces as $item)
                            <option value="{{ $item->urlSlug() }}" @selected($province === $item)>{{ $item->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dir-field">
                    <span>Town</span>
                    <input type="search" name="town" value="{{ $town }}" placeholder="Town or area">
                </label>
                <label class="dir-check">
                    <input type="checkbox" name="visitors" value="1" @checked($visitors)>
                    <span>Only show clubs that welcome visitors</span>
                </label>
                <button class="btn dir-filter" type="submit">Filter</button>
            </form>

            <p class="dir-count"><strong>{{ $clubs->count() }} {{ \Illuminate\Support\Str::plural('club', $clubs->count()) }}</strong></p>

            <div class="dir-split is-clubs">
                <div class="dir-list">
                    @forelse ($clubs as $club)
                        @php
                            $initials = collect(preg_split('/\s+/', $club->name))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->implode('');
                        @endphp
                        <a class="dir-row" href="{{ route('clubs.show', $club->slug) }}">
                            @if ($club->logoUrl())
                                <img class="dir-logo" src="{{ $club->logoUrl() }}" alt="">
                            @else
                                <span class="dir-initials">{{ $initials }}</span>
                            @endif
                            <span class="dir-main">
                                <strong>{{ $club->name }}</strong>
                                <span>{{ collect([$club->type->getLabel(), $club->town, $club->province?->getLabel()])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="dir-chev" aria-hidden="true">›</span>
                        </a>
                    @empty
                        <div class="empty">
                            <p>No clubs match these filters.</p>
                            {{-- Useful next actions instead of a dead
                                 end: clearing filters and browsing the
                                 wider register both bring the shooter
                                 back somewhere they can act. --}}
                            <p style="margin-top:10px">
                                <a class="btn ghost" href="{{ route('clubs.index') }}">Clear filters</a>
                                <a class="btn ghost" href="{{ route('disciplines.index') }}">Explore shooting sports</a>
                            </p>
                        </div>
                    @endforelse
                </div>
                <aside class="dir-aside">
                    <div class="dir-note">
                        <h2>List your club</h2>
                        <p>Get your club onto the register and reach shooters across South Africa.</p>
                        <a class="btn dir-line" href="{{ route('claim') }}">List your club</a>
                    </div>
                    <div class="dir-note">
                        <h2>New to shooting?</h2>
                        <p>Not sure where to start? Learn about the different shooting disciplines and how to get involved.</p>
                        <a href="{{ route('disciplines.index') }}">Explore shooting sports</a>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.public>
