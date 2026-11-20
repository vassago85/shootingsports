<x-layouts.public :title="$seoTitle" :description="$seoDescription" :canonical="$canonical" :robots="$robots" :json-ld="$jsonLd">
    <main id="main">
        <div class="wrap">
            @php
                $categoryCount = count($categories);
                $businessCount = collect($grouped)->flatten(1)->unique('id')->count();
                $heroUrl = \App\Support\PageHeroes::url('industry');
            @endphp
            <header class="dir-hero">
                <div class="dir-hero-copy">
                    <p class="label">Directory</p>
                    <h1>Industry</h1>
                    <div class="lede">
                        <p>
                            Every shooting-related business, listed free. We do not sell firearms or take a cut of a transfer.
                            @if ($division)
                                Showing suppliers linked to {{ $division->getLabel() }} sports.
                            @endif
                        </p>
                        <p class="text-sm">{{ $businessCount }} businesses across {{ $categoryCount }} categories</p>
                    </div>
                </div>
                @if (filled($heroUrl))
                    <div class="dir-hero-art" style="--hero-image: url('{{ str_replace(['\\', "'"], ['/', ''], $heroUrl) }}'); background-position: center 42%" role="img" aria-label="A machinist working a metal lathe"></div>
                @endif
            </header>
        </div>
        <section class="block" style="padding-top:0">
            <div class="wrap">
                {{-- Search sits above the category grid so a shooter
                     who already knows the business can find it without
                     picking the right category first. --}}
                <form class="dir-filters mt-10" method="get" action="{{ route('suppliers.index') }}" style="margin-bottom:20px">
                    @if ($division)
                        <input type="hidden" name="division" value="{{ $division->value }}">
                    @endif
                    <label class="dir-field" style="flex:1;min-width:220px">
                        <span>Search industry</span>
                        <input type="search" name="q" value="{{ $search }}" placeholder="Business, service or town">
                    </label>
                    <button class="btn dir-filter" type="submit">Search</button>
                    @if ($search !== '')
                        <a class="btn ghost" href="{{ route('suppliers.index', $division ? ['division' => $division->value] : []) }}">Clear</a>
                    @endif
                </form>

                @if ($search !== '')
                    <section class="supplier-search-results" style="margin:0 0 32px">
                        <p class="label">{{ $matches->count() }} {{ \Illuminate\Support\Str::plural('result', $matches->count()) }}</p>
                        <h2 style="margin:4px 0 14px">Search results for "{{ $search }}"</h2>
                        @if ($matches->isEmpty())
                            <div class="empty">
                                <p>No suppliers match "{{ $search }}" yet.</p>
                                <p style="margin-top:10px">
                                    <a class="btn ghost" href="{{ route('suppliers.index') }}">Clear search</a>
                                    <a class="btn ghost" href="{{ route('suppliers.onboard') }}">List a business</a>
                                </p>
                            </div>
                        @else
                            <div class="dope-grid">
                                @foreach ($matches as $match)
                                    <a class="listing" href="{{ route('suppliers.show', $match->slug) }}">
                                        <h3>{{ $match->name }}</h3>
                                        <p class="meta">
                                            {{ $match->category->getLabel() }}
                                            @if (filled($match->town) || $match->province)
                                                · {{ collect([$match->town, $match->province?->getLabel()])->filter()->implode(', ') }}
                                            @endif
                                        </p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                <div class="disc-grid">
                    @foreach ($categories as $category)
                        @php $count = ($grouped[$category->value] ?? collect())->count(); @endphp
                        <a class="disc {{ $count === 0 ? 'is-quiet' : '' }}" href="{{ route('suppliers.category', $category->urlSlug()) }}">
                            <span class="fam">Category</span>
                            <span class="nm">{{ $category->getLabel() }}</span>
                            @if ($count > 0)
                                <span class="ct">{{ $count }} listed</span>
                            @else
                                <span class="ct ct-quiet">Free to list. Open this category</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                {{-- UX audit #13: ad-slot below the category grid + hide
                     when vacant. The old placement above the grid was
                     the first thing a visitor saw on the Industry page,
                     and it was a house ad for empty inventory. --}}
                <x-ad-slot page="suppliers" placement-slot="in_feed_native" :limit="2" class="ss-partner--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
