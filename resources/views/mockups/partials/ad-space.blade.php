@php
    $booked = collect($sponsors ?? [])
        ->filter(fn (array $sponsor): bool => filled($sponsor['image'] ?? null))
        ->take($limit ?? 1)
        ->values();
@endphp
@if ($booked->isNotEmpty())
    <aside class="ss-partner ss-partner--tight" aria-label="Partner">
        <p class="ss-partner-label">Sponsored</p>
        <div class="ss-partner-stack">
            @foreach ($booked as $sponsor)
                @php
                    $publicProfile = $sponsor['public'] ?? true;
                    $href = $publicProfile
                        ? $mk('mockups.business', ['slug' => $sponsor['slug']])
                        : ($sponsor['website'] ?? null);
                    $alt = $sponsor['headline'] ?: ($sponsor['name'] ?? '');
                @endphp
                <article class="ss-partner-card ss-partner-banner">
                    @if ($href)
                        <a href="{{ $href }}" @if (! $publicProfile) target="_blank" rel="sponsored noopener noreferrer" @else rel="sponsored" @endif class="ss-partner-media">
                            <img src="{{ $sponsor['image'] }}" alt="{{ $alt }}">
                        </a>
                    @else
                        <div class="ss-partner-media"><img src="{{ $sponsor['image'] }}" alt="{{ $alt }}"></div>
                    @endif
                </article>
            @endforeach
        </div>
    </aside>
@endif
