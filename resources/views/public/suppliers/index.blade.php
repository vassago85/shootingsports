<x-layouts.public title="Suppliers" description="Gunsmiths, dealers, instructors and other providers listed on the South African shooting sports register.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Directory</p>
                <h1>Suppliers</h1>
                <p>Listings are free. We do not sell firearms or take a cut of a transfer.</p>
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
