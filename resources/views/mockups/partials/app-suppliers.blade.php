@if ($screen === 'suppliers')
    @php
        $category = trim(request()->string('category')->toString());
        $categories = $businesses->pluck('category')->filter()->unique()->values();
        $shown = $category !== ''
            ? $businesses->filter(fn (array $row): bool => $row['category'] === $category)->values()
            : $businesses;
    @endphp
    <div class="app-pad">
        <h1 class="app-hero">Suppliers</h1>
        <form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
            <input type="hidden" name="screen" value="suppliers">
            <label class="app-search-field">
                <x-mockups.icon name="search" />
                <input type="search" name="q" placeholder="Search suppliers" aria-label="Search suppliers">
            </label>
        </form>
        @if ($categories->isNotEmpty())
            <div class="app-chipbar" role="group" aria-label="Category">
                <a href="{{ $app('suppliers', ['category' => null]) }}" @class(['app-chip', 'on' => $category === ''])>All</a>
                @foreach ($categories->take(8) as $name)
                    <a href="{{ $app('suppliers', ['category' => $name]) }}" @class(['app-chip', 'on' => $category === $name])>{{ $name }}</a>
                @endforeach
            </div>
        @endif

        @include('mockups.partials.app-banners', ['banners' => ($category === '' ? $bannersFor('suppliers') : collect())->take(1)])

        <p class="app-sub">{{ $category !== '' ? $category : 'Near you' }}</p>
        @forelse ($shown->take(20) as $row)
            <a class="app-row" href="{{ $app('supplier', ['supplier' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon name="store" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['category'], $row['place']])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
                <x-mockups.icon name="chevron" class="app-chev" />
            </a>
        @empty
            <p class="app-empty"><strong>No suppliers</strong>None are listed for this yet.</p>
        @endforelse
    </div>

@elseif ($screen === 'supplier')
    <div class="app-pad">
        @if ($supplier)
            @if (! $supplier['public'])
                <p class="app-kicker">Advertising account</p>
                <h1 class="app-hero">{{ $supplier['name'] }}</h1>
                <p class="app-lead">This account is for advertising, not a public listing.</p>
                @if ($supplier['website'])<a class="app-btn" href="{{ $supplier['website'] }}" target="_blank" rel="noopener">Website</a>@endif
            @else
                @if ($supplier['featured'])<span class="app-sponsor-label">Sponsored</span>@endif
                <h1 class="app-hero">{{ $supplier['name'] }}</h1>
                <p class="app-lead">{{ collect([$supplier['category'], $supplier['place']])->filter()->implode(' · ') }}</p>
                @if ($supplier['verified'])<p class="app-banner">Verified on the register.</p>@endif
                @if ($supplier['description'])<p class="app-lead">{{ $supplier['description'] }}</p>@endif
                @if ($supplier['services'] !== [])
                    <p class="app-sub">Services</p>
                    <div class="app-follows">
                        @foreach ($supplier['services'] as $service)
                            <span class="app-follow off">{{ $service }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="app-stack">
                    @if ($supplier['website'])<a class="app-btn" href="{{ $supplier['website'] }}" target="_blank" rel="noopener">Website</a>@endif
                    @if ($supplier['phone'])<a class="app-btn secondary" href="tel:{{ $supplier['phone'] }}">Call</a>@endif
                    @if ($supplier['email'])<a class="app-btn secondary" href="mailto:{{ $supplier['email'] }}">Email</a>@endif
                </div>
            @endif
        @else
            <h1 class="app-hero">No supplier open</h1>
            <a class="app-btn" href="{{ $app('suppliers') }}">All suppliers</a>
        @endif
    </div>
@endif
