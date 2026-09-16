<x-layouts.public :title="$seoTitle" :description="$seoDescription" :json-ld="$jsonLd">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Directory</p>
                <h1>Industry</h1>
                <p>Every shooting-related business, listed free. We do not sell firearms or take a cut of a transfer.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <div class="disc-grid">
                    @foreach ($categories as $category)
                        @php $count = ($grouped[$category->value] ?? collect())->count(); @endphp
                        @if ($count > 0)
                            <a class="disc" href="{{ route('suppliers.category', $category->urlSlug()) }}">
                                <span class="fam">Category</span>
                                <span class="nm">{{ $category->getLabel() }}</span>
                                <span class="ct">{{ $count }} listed</span>
                            </a>
                        @else
                            {{-- Bundle A #1: empty categories sell the
                                 claim flow, not a hollow "0 listed". --}}
                            <a class="disc is-quiet" href="{{ route('claim') }}">
                                <span class="fam">Category</span>
                                <span class="nm">{{ $category->getLabel() }}</span>
                                <span class="ct ct-quiet">Free to list — claim this category</span>
                            </a>
                        @endif
                    @endforeach
                </div>
                {{-- UX audit #13: ad-slot below the category grid + hide
                     when vacant. The old placement above the grid was
                     the first thing a visitor saw on the Industry page,
                     and it was a house ad for empty inventory. --}}
                <x-ad-slot page="suppliers" placement-slot="in_feed_native" :limit="2" class="ad-rail--tight" hide-when-vacant />
            </div>
        </section>
    </main>
</x-layouts.public>
