<x-layouts.public
    :title="$province ? $category->getLabel().' in '.$province->getLabel() : $category->getLabel()"
    :description="'Suppliers in '.$category->getLabel().($province ? ' — '.$province->getLabel() : '').'.'"
    :robots="$noindex ? 'noindex,follow' : null"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label"><a href="{{ route('suppliers.index') }}">Suppliers</a> / {{ $category->getLabel() }}</p>
                <h1>{{ $category->getLabel() }}@if ($province) in {{ $province->getLabel() }}@endif</h1>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <div class="filters">
                    <a href="{{ route('suppliers.category', $category->urlSlug()) }}" class="{{ $province === null ? 'on' : '' }}">All provinces</a>
                    @foreach ($provinces as $item)
                        <a href="{{ route('suppliers.province', [$category->urlSlug(), $item->urlSlug()]) }}" class="{{ $province === $item ? 'on' : '' }}">{{ $item->getLabel() }}</a>
                    @endforeach
                </div>
                @forelse ($providers as $provider)
                    <a class="listing" href="{{ route('suppliers.show', $provider->slug) }}">
                        <h3>{{ $provider->name }} <x-listing-tier-badge :listing="$provider" /></h3>
                        <p class="meta">{{ $provider->town }} · {{ $provider->province?->getLabel() }}</p>
                        <x-verification-badge :listing="$provider" />
                    </a>
                @empty
                    <p class="empty">
                        Free to list — <a href="{{ route('claim') }}">claim this category</a>
                        and your business appears here.
                    </p>
                @endforelse
            </div>
        </section>
    </main>
</x-layouts.public>
