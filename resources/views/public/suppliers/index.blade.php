<x-layouts.public title="Industry" description="Gunsmiths, dealers, ammunition, optics and other shooting-related businesses listed on the South African register.">
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
                <x-ad-slot page="suppliers" placement-slot="in_feed_native" :limit="2" class="ad-rail--tight" />
                <div class="disc-grid">
                    @foreach ($categories as $category)
                        <a class="disc" href="{{ route('suppliers.category', $category->urlSlug()) }}">
                            <span class="fam">Category</span>
                            <span class="nm">{{ $category->getLabel() }}</span>
                            <span class="ct">{{ ($grouped[$category->value] ?? collect())->count() }} listed</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
