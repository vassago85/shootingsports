<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Magazine</p>
                <h1>Magazines</h1>
                <p>Read South African shooting sports magazines free online.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <form class="magazine-filters" method="get" action="{{ route('magazines.index') }}">
                    <label>
                        <span>Publication</span>
                        <select name="publication">
                            <option value="">All publications</option>
                            @foreach ($publications as $name)
                                <option value="{{ $name }}" @selected($publication === $name)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Year</span>
                        <select name="year">
                            <option value="">All years</option>
                            @foreach ($years as $option)
                                <option value="{{ $option }}" @selected($year === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn">Filter</button>
                    @if ($publication || $year)
                        <a href="{{ route('magazines.index') }}">Clear filters</a>
                    @endif
                </form>

                @if ($issues->isEmpty())
                    <p class="empty">
                        @if ($publication || $year)
                            No issues match these filters.
                            <a href="{{ route('magazines.index') }}">Show every issue</a>
                        @else
                            The first issue is on its way.
                        @endif
                    </p>
                @else
                    @php
                        $hero = $issues->first();
                    @endphp

                    <div class="magazine-grid">
                        @foreach ($issues as $issue)
                            @include('public.magazines._card', [
                                'issue' => $issue,
                                'hero' => $issue->is($hero),
                                'continuePage' => $continue[$issue->id] ?? null,
                            ])
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
