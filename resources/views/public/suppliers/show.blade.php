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
                <p class="label">{{ $provider->category->getLabel() }} · {{ $provider->province?->getLabel() }}</p>
                <h1>{{ $provider->name }} <x-listing-tier-badge :listing="$provider" /></h1>
                <p>{{ $provider->description }}</p>
                <x-verification-badge :listing="$provider" />
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <dl class="dope-rows" style="max-width:420px;padding:0">
                    <div class="r"><dt>Town</dt><dd>{{ $provider->town }}</dd></div>
                    <div class="r"><dt>Province</dt><dd>{{ $provider->province?->getLabel() }}</dd></div>
                    @if ($provider->serviceCategories()->isNotEmpty())
                        <div class="r"><dt>Also offers</dt><dd>{{ $provider->serviceCategories()->map(fn ($c) => $c->getLabel())->implode(', ') }}</dd></div>
                    @endif
                    @if ($provider->tier && $provider->tier->value !== 'free')
                        <div class="r"><dt>Listing</dt><dd>{{ $provider->tier->getLabel() }}</dd></div>
                    @endif
                </dl>
                <p style="margin-top:18px;display:flex;flex-wrap:wrap;gap:12px">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'provider', 'id' => $provider->id]) }}">Enquire via platform</a>
                    @if ($provider->website_url)
                        <a class="btn ghost" href="{{ $provider->website_url }}" rel="noopener noreferrer">Website</a>
                    @endif
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
