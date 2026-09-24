<x-layouts.public title="Listing submitted" description="Your supplier listing is in for review.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Listing submitted</p>
                <h1>Thanks. Your listing is in for review</h1>
                <p>Staff usually publish within one working day, and you will get an email as soon as your listing goes live in the Industry directory.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                <dl class="dope-rows" style="max-width:520px;padding:0">
                    <div class="r"><dt>Business</dt><dd>{{ $provider->name }}</dd></div>
                    <div class="r"><dt>Primary category</dt><dd>{{ $provider->category?->getLabel() }}</dd></div>
                    @if ($provider->serviceCategories()->isNotEmpty())
                        <div class="r"><dt>Other services</dt><dd>{{ $provider->serviceCategories()->map(fn ($c) => $c->getLabel())->implode(', ') }}</dd></div>
                    @endif
                    <div class="r"><dt>Province</dt><dd>{{ $provider->province?->getLabel() }}</dd></div>
                    <div class="r"><dt>Town</dt><dd>{{ $provider->town }}</dd></div>
                    @if (filled($provider->tagline))
                        <div class="r"><dt>Short description</dt><dd>{{ $provider->tagline }}</dd></div>
                    @endif
                    <div class="r"><dt>Status</dt><dd>{{ $provider->status?->getLabel() }}</dd></div>
                </dl>

                <p style="margin-top:22px">
                    <a class="btn" href="{{ route('suppliers.onboard.edit', $provider) }}">Add a logo or short description</a>
                </p>
                <p style="margin-top:14px;color:var(--slate)">
                    Need a category or town change? <a href="{{ route('contact') }}">Email us</a> and we will update it.
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
