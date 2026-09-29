@php
    $app = fn (string $screen, array $extra = []): string => route('mockups.apps', array_merge(['screen' => $screen], $extra));

    $notes = [
        'onboarding' => 'Location is positioned as useful, not mandatory. Denial falls back to province and town. Sport selection is neutral — clay, archery, PRS, IPSC and rimfire from the start.',
        'home' => 'The weekend module is the reason to open the app on Friday. Followed interests turn the national calendar into a personalized, low-noise feed.',
        'events' => 'Filters are first-class, not hidden in a tiny drawer. The list stays information-dense for repeat use.',
        'detail' => 'Event detail prioritises decision data. Directions, calendar, save and share sit next to when and where. External entry stays a launch-out action.',
        'find' => 'Find combines map and list for clubs, ranges, events and industry. Category chips switch mode without losing geography.',
        'sports' => 'Sports are grouped as approachable disciplines, not equipment. Following a sport feeds Home, Events defaults and notifications.',
        'following' => 'Saved events and followed interests live together. Changed and cancelled saved events stay pinned with explicit status.',
        'activity' => 'Activity is practical: new events, entry windows, reminders, changes and cancellations. Notification settings sit next to the feed.',
        'profile' => 'Profile is mostly preferences and account state. Logged-out browsing is clear; save, follow, notifications and organiser submissions ask for sign-in.',
        'organiser' => 'Organiser tools are mobile-first: draft, add essentials, submit, then manage changes. Published changes notify saved and followed users.',
    ];
@endphp

<x-layouts.public
    title="App"
    description="Companion-app mockups for the ShootingSports register: iPhone and Android, weekend-first."
    robots="noindex, nofollow"
>
    <style>{!! file_get_contents(resource_path('css/mockups.css')) !!}</style>
    <main id="main" class="app-review">
        <section class="app-hero-block">
            <div class="wrap">
                <p class="eyebrow">Companion app</p>
                <h1>Weekend-first mobile mockups.</h1>
                <p>Native-feeling iPhone and Android screens for ShootingSports. Live matches, clubs, ranges and disciplines, wrapped in the companion-app design.</p>
                <div class="app-chips">
                    <span class="app-chip">10 screens</span>
                    <span class="app-chip">iPhone &amp; Android</span>
                    <span class="app-chip">Live register data</span>
                </div>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <nav class="screen-nav" aria-label="Screen picker">
                    @foreach ($journey as $i => $item)
                        <a href="{{ $app($item['key']) }}" @class(['on' => $screen === $item['key']])>
                            <span class="n">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="app-canvas">
                    <x-mockups.phone platform="ios" :screen="$screen">
                        @include('mockups.partials.app-screen')
                    </x-mockups.phone>
                    <x-mockups.phone platform="android" :screen="$screen">
                        @include('mockups.partials.app-screen')
                    </x-mockups.phone>
                </div>

                @if (isset($notes[$screen]))
                    <p class="app-note"><strong>Design note.</strong> {{ $notes[$screen] }}</p>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
