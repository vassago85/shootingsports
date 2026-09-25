<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :json-ld="$jsonLd"
>
    <main id="main" class="supplier-profile">
        <div class="wrap">
            <p class="supplier-crumb">
                <a href="{{ route('suppliers.index') }}">Industry</a>
                /
                <a href="{{ route('suppliers.category', $provider->category->urlSlug()) }}">{{ $provider->category->getLabel() }}</a>
                @if (filled($provider->town) || $provider->province)
                    / {{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }}
                @endif
            </p>

            <section class="supplier-head">
                <div>
                    <p class="label">{{ $provider->category->getLabel() }}</p>
                    <h1>{{ $provider->name }} <x-listing-tier-badge :listing="$provider" /></h1>
                    <p class="supplier-where">{{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }} · Shooting industry listing</p>
                    @if (filled($provider->tagline))
                        <p class="supplier-pitch">{{ $provider->tagline }}</p>
                    @endif
                    <p class="supplier-actions">
                        <a class="btn" href="{{ route('enquiries.listing', ['type' => 'provider', 'id' => $provider->id]) }}">Enquire via platform</a>
                        @if ($provider->website_url)
                            <a class="btn ghost" href="{{ $provider->website_url }}" rel="noopener noreferrer">Website</a>
                        @endif
                        @auth
                            @if ($provider->claimed_by === auth()->id())
                                <a class="btn ghost" href="{{ route('suppliers.onboard.edit', $provider) }}">Edit your listing</a>
                            @elseif ($provider->claimed_by === null)
                                <a class="btn ghost" href="{{ route('suppliers.claim', $provider) }}">This is my business</a>
                            @endif
                        @else
                            @if ($provider->claimed_by === null)
                                <a class="btn ghost" href="{{ route('suppliers.claim', $provider) }}">This is my business</a>
                            @endif
                        @endauth
                    </p>
                    <x-verification-badge :listing="$provider" />
                </div>
                <div class="supplier-logo-card">
                    @if ($provider->logoUrl())
                        <img src="{{ $provider->logoUrl() }}" alt="{{ $provider->name }} logo">
                    @else
                        <span class="supplier-initials" aria-hidden="true">{{ $provider->initials() }}</span>
                        <span class="supplier-logo-hint">Business logo</span>
                    @endif
                </div>
            </section>

            @php $offers = $provider->offeredCategories(); @endphp
            @if ($offers->isNotEmpty())
                <section class="supplier-block">
                    <p class="label">What they offer</p>
                    <h2>Services and products</h2>
                    <div class="supplier-services">
                        @foreach ($offers as $service)
                            <a class="supplier-service {{ $service === $provider->category ? 'is-primary' : '' }}" href="{{ route('suppliers.category', $service->urlSlug()) }}">
                                <span class="supplier-service-role">{{ $service === $provider->category ? 'Primary' : 'Secondary' }}</span>
                                <span class="supplier-service-name">{{ $service->getLabel() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="supplier-block supplier-details">
                <div>
                    @if (filled($provider->description))
                        <p class="label">Profile</p>
                        <h2>About {{ $provider->name }}</h2>
                        <div class="supplier-prose">
                            @foreach (preg_split("/\n{2,}/", trim($provider->description)) ?: [] as $paragraph)
                                @if (filled(trim($paragraph)))
                                    <p>{{ trim($paragraph) }}</p>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="supplier-place">
                    <p class="label">Location</p>
                    <h2>{{ $provider->town ?: 'South Africa' }}</h2>
                    <strong>{{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }}</strong>
                    <p>South Africa</p>
                    <div class="supplier-enquiry">
                        <p class="label">Contact</p>
                        <h2>Enquire with {{ $provider->name }}</h2>
                        <p>Send an enquiry through the register. Your details are only shared with the business when you submit.</p>
                        <a class="btn" href="{{ route('enquiries.listing', ['type' => 'provider', 'id' => $provider->id]) }}">Send an enquiry</a>
                    </div>
                    <x-ad-slot page="suppliers" placement-slot="in_feed_native" :limit="1" hide-when-vacant />
                </div>
            </section>
        </div>
    </main>
</x-layouts.public>
