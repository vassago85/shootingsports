<x-layouts.coming-soon
    :title="$title"
    description="ShootingSports pre-launch interest confirmation."
>
    <div class="coming-soon">
        <div class="cs-grid" aria-hidden="true"></div>

        <header class="cs-nav">
            <a class="cs-brand" href="{{ route('coming-soon') }}">
                <span class="cs-brand-text">
                    <span class="cs-wordmark">ShootingSports</span>
                    <span class="cs-sub">The SA Register</span>
                </span>
            </a>
        </header>

        <main class="cs-main" id="main">
            <p class="cs-label">
                <span class="cs-dot" aria-hidden="true"></span>
                {{ $ok ? 'Confirmed' : 'Almost there' }}
            </p>
            <h1 class="cs-headline">{{ $title }}</h1>
            <p class="cs-lede">{{ $message }}</p>
            <p class="cs-cta">
                <a href="{{ route('coming-soon') }}">Back to coming soon</a>
            </p>
        </main>

        <footer class="cs-foot">
            <span>&copy; {{ date('Y') }} ShootingSports</span>
            <span>shootingsports.co.za</span>
        </footer>
    </div>
</x-layouts.coming-soon>
