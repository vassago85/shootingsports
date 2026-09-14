<x-layouts.public
    :title="$provider->name"
    :description="$provider->description ?: $provider->name.' — '.$provider->category->getLabel()"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ $provider->category->getLabel() }} · {{ $provider->province?->getLabel() }}</p>
                <h1>{{ $provider->name }}</h1>
                <p>{{ $provider->description }}</p>
                <x-verification-badge :listing="$provider" />
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <dl class="dope-rows" style="max-width:420px;padding:0">
                    <div class="r"><dt>Town</dt><dd>{{ $provider->town }}</dd></div>
                    <div class="r"><dt>Province</dt><dd>{{ $provider->province?->getLabel() }}</dd></div>
                    @if ($provider->phone)
                        <div class="r"><dt>Phone</dt><dd>{{ $provider->phone }}</dd></div>
                    @endif
                    @if ($provider->email)
                        <div class="r"><dt>Email</dt><dd>{{ $provider->email }}</dd></div>
                    @endif
                </dl>
                @if ($provider->website_url)
                    <p style="margin-top:18px"><a class="label" href="{{ $provider->website_url }}" rel="noopener noreferrer" style="border-bottom:1px solid var(--brass);text-decoration:none">Website →</a></p>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
