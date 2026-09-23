<x-mockups.v2.layout title="Shooting industry" active="industry">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Industry</p>
                <h1>Shooting industry</h1>
                <p class="v2-lede">Search businesses and suppliers supporting South African shooting sports. Sponsored listings are labelled. They are not mixed into organic rank.</p>
            </div>
            <div class="v2-hero-art is-range" role="img" aria-label="Outdoor shooting range"></div>
        </header>
        <form class="v2-filters cols-3" method="get">
            @include('mockups.v2.partials.keep')
            <label class="v2-field"><span>Search</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Name or service"></label>
            <label class="v2-field"><span>Category</span>
                <select name="category">
                    <option value="">Any category</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field"><span>Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="v2-btn v2-btn-green" type="submit">Search</button>
        </form>
        @php
            $featured = $businesses->filter(fn (array $business): bool => $business['featured']);
            $organic = $businesses->reject(fn (array $business): bool => $business['featured']);
        @endphp
        @if ($featured->isNotEmpty())
            <h2 style="font-size:18px;margin:8px 0">Sponsored</h2>
            <div class="v2-list" style="margin-bottom:18px">
                @foreach ($featured as $business)
                    <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.business', ['slug' => $business['slug']]) }}">
                        <span>
                            <span class="v2-name">{{ $business['name'] }} <span class="v2-badge sponsored">Sponsored</span></span>
                            <span class="v2-sport">{{ collect([$business['category'], $business['place']])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ') }}</span>
                        </span>
                        <span></span>
                        <span class="v2-chev">›</span>
                    </a>
                @endforeach
            </div>
        @endif
        <h2 style="font-size:18px;margin:8px 0">Directory</h2>
        <div class="v2-list">
            @forelse ($organic as $business)
                <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ $mk('mockups.v2.business', ['slug' => $business['slug']]) }}">
                    <span>
                        <span class="v2-name">{{ $business['name'] }}</span>
                        @if (filled($business['description']))<span class="v2-sport">{{ \Illuminate\Support\Str::limit($business['description'], 140) }}</span>@endif
                    </span>
                    <span class="v2-side">{{ collect([$business['category'], $business['place']])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ') }}</span>
                    <span class="v2-chev">›</span>
                </a>
            @empty
                <p class="v2-empty"><strong>No businesses match this search.</strong></p>
            @endforelse
        </div>
    </div>
</x-mockups.v2.layout>
