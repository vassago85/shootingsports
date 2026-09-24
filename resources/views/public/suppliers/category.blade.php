<x-layouts.public
    :title="$province ? $category->getLabel().' in '.$province->getLabel() : $category->getLabel()"
    :description="'Suppliers in '.$category->getLabel().($province ? ', '.$province->getLabel() : '').'.'"
    :robots="$noindex ? 'noindex,follow' : null"
    :json-ld="$jsonLd"
>
    <main id="main" class="dir-page">
        <div class="wrap">
            <header class="dir-hero is-copy">
                <div class="dir-hero-copy">
                    <p class="label"><a href="{{ route('suppliers.index') }}">Industry</a> / {{ $category->getLabel() }}</p>
                    <h1>{{ $category->getLabel() }}@if ($province) in {{ $province->getLabel() }}@endif</h1>
                    <p class="lede">{{ $providers->count() }} {{ \Illuminate\Support\Str::plural('business', $providers->count()) }} listed.</p>
                </div>
            </header>
            <div class="filters">
                <a href="{{ route('suppliers.category', $category->urlSlug()) }}" class="{{ $province === null ? 'on' : '' }}">All provinces</a>
                @foreach ($provinces as $item)
                    <a href="{{ route('suppliers.province', [$category->urlSlug(), $item->urlSlug()]) }}" class="{{ $province === $item ? 'on' : '' }}">{{ $item->getLabel() }}</a>
                @endforeach
            </div>
            <div class="dir-split is-clubs">
                <div class="dir-list">
                    @forelse ($providers as $provider)
                        <x-supplier-row :provider="$provider" />
                    @empty
                        <p class="empty">
                            Free to list. <a href="{{ route('claim') }}">Claim this category</a>
                            and your business appears here.
                        </p>
                    @endforelse
                </div>
                <aside class="dir-aside">
                    <div class="dir-note">
                        <h2>List your business</h2>
                        <p>Gunsmiths, dealers, and instructors can list free. Staff review the page before it goes live.</p>
                        <a class="btn" href="{{ route('claim') }}">Claim this category</a>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.public>
