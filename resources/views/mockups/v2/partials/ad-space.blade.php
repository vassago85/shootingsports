@php
    $booked = collect($sponsors ?? [])
        ->filter(fn (array $sponsor): bool => filled($sponsor['image'] ?? null))
        ->take($limit ?? 1)
        ->values();
@endphp
@if ($booked->isNotEmpty())
    <aside class="v2-partner" aria-label="Partner">
        <p class="v2-partner-label">Sponsored</p>
        <div class="v2-partner-stack">
            @foreach ($booked as $sponsor)
                @php
                    $publicProfile = $sponsor['public'] ?? true;
                    $href = $publicProfile
                        ? $mk('mockups.v2.business', ['slug' => $sponsor['slug']])
                        : ($sponsor['website'] ?? null);
                    $alt = $sponsor['headline'] ?: ($sponsor['name'] ?? '');
                @endphp
                @if ($href)
                    <a class="v2-partner-media" href="{{ $href }}" @if (! $publicProfile) target="_blank" rel="sponsored noopener noreferrer" @else rel="sponsored" @endif>
                        <img src="{{ $sponsor['image'] }}" alt="{{ $alt }}">
                    </a>
                @else
                    <div class="v2-partner-media"><img src="{{ $sponsor['image'] }}" alt="{{ $alt }}"></div>
                @endif
            @endforeach
        </div>
    </aside>
@endif
