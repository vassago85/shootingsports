<x-mockups.layout title="Shooting industry" active="industry">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Industry</p>
            <h1>Shooting industry</h1>
            <p class="mk-lede">Search businesses and suppliers supporting South African shooting sports. Sponsored listings are labelled. They are not mixed into organic rank.</p>
        </header>
        <form class="mk-filters" method="get">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            <label class="field grow">
                <span>Search</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Name or service">
            </label>
            <label class="field">
                <span>Category</span>
                <select name="category">
                    <option value="">Any category</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn" type="submit">Search</button>
        </form>
        <p class="mk-support">Categories on the register include dealers, gunsmiths, ammunition, reloading, optics, chassis and stocks, ballistics, range equipment and instructors. Distributors are advertising accounts, not public listings. Only categories with a published business appear in the filter.</p>
        @php
            $featured = $businesses->filter(fn (array $business): bool => $business['featured']);
            $organic = $businesses->reject(fn (array $business): bool => $business['featured']);
        @endphp
        @if ($featured->isNotEmpty())
            <h2 style="margin-top:22px;font-size:18px;letter-spacing:.12em;text-transform:uppercase">Sponsored</h2>
            @foreach ($featured as $business)
                <a class="mk-row" href="{{ $mk('mockups.business', ['slug' => $business['slug']]) }}">
                    <span>
                        <span class="mk-badge sponsored">Sponsored</span>
                        <span class="mk-row-title">{{ $business['name'] }}</span>
                    </span>
                    <span class="mk-row-meta">{{ $business['category'] }} · {{ $business['place'] }}</span>
                </a>
            @endforeach
        @endif
        <h2 style="margin-top:22px;font-size:18px;letter-spacing:.12em;text-transform:uppercase">Directory</h2>
        @forelse ($organic as $business)
            <a class="mk-row" href="{{ $mk('mockups.business', ['slug' => $business['slug']]) }}">
                <span>
                    <span class="mk-row-title">{{ $business['name'] }}</span>
                    <span class="mk-sub">{{ \Illuminate\Support\Str::limit($business['description'], 140) }}</span>
                </span>
                <span class="mk-row-meta">{{ $business['category'] }} · {{ $business['place'] }}</span>
                <span class="mk-count">{{ $business['verified'] ? 'Verified' : 'Unverified' }}</span>
            </a>
        @empty
            <p class="mk-empty"><strong>No businesses match this search.</strong></p>
        @endforelse
    </div>
</x-mockups.layout>
