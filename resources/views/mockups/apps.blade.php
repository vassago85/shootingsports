@php
    $app = fn (string $screen, array $extra = []): string => route('mockups.apps', array_merge(['screen' => $screen], $extra));
@endphp

<x-layouts.public title="App" description="Phone mockup of the ShootingSports register, from login through the signed-in functions." robots="noindex, nofollow">
    <style>{!! file_get_contents(resource_path('css/mockups.css')) !!}</style>
    <main id="main" class="app-review">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">App</p>
                <h1>ShootingSports on iPhone &amp; Android</h1>
                <p>The same register as the website, starting at login. Home, matches, your calendar and log, find, and account.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap app-shell">
                <aside class="app-sidebar" aria-label="App journey">
                    @php $n = 0; @endphp
                    @foreach ($journey as $group)
                        <h2>{{ $group['group'] }}</h2>
                        @foreach ($group['screens'] as $item)
                            @php $n++; @endphp
                            <a href="{{ $app($item['key']) }}" @class(['on' => $screen === $item['key']])>
                                <span>{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</span>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </aside>
                <div class="app-canvas">
                    <x-mockups.phone platform="ios" :screen="$screen">
                        @include('mockups.partials.app-screen')
                    </x-mockups.phone>
                    <x-mockups.phone platform="android" :screen="$screen">
                        @include('mockups.partials.app-screen')
                    </x-mockups.phone>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
