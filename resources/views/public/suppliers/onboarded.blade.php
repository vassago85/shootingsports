<x-layouts.public title="Your business" description="Your supplier listing on Shooting Sports.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My business</p>
                @if ($provider->status === \App\Enums\ListingStatus::Published)
                    <h1>{{ $provider->name }} is live</h1>
                    <p>Visitors can find this listing in the Industry directory. Updates to the name, contact details, and description show as soon as you save them.</p>
                @else
                    <h1>Thanks. Your listing is in for review</h1>
                    <p>Staff usually publish within one working day, and you will get an email as soon as your listing goes live in the Industry directory.</p>
                @endif
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

                <p style="margin-top:22px;display:flex;flex-wrap:wrap;gap:12px">
                    <a class="btn" href="{{ route('suppliers.onboard.edit', $provider) }}">Update your listing</a>
                    @if ($provider->status === \App\Enums\ListingStatus::Published)
                        <a class="btn ghost" href="{{ route('suppliers.show', $provider) }}">View public page</a>
                    @endif
                </p>
                @if (auth()->user()?->isMdPending())
                    <p style="margin-top:18px">
                        <a class="btn ghost" href="{{ route('matches.submit') }}">Submit your match</a>
                    </p>
                    <p style="margin-top:8px;color:var(--slate)">You also asked to publish matches. The match stays off the calendar until we approve it.</p>
                @endif
                <p style="margin-top:14px;color:var(--slate)">
                    Need a category or town change? <a href="{{ route('contact') }}">Email us</a> and we will update it.
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
