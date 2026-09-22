@php
    $booked = collect($sponsors ?? [])->take($limit ?? 1)->values();
@endphp
<aside class="ss-partner ss-partner--tight" aria-label="Partner">
    @if ($booked->isNotEmpty())
        <p class="ss-partner-label">Sponsored</p>
        <div class="ss-partner-stack">
            @foreach ($booked as $sponsor)
                @php
                    $publicProfile = $sponsor['public'] ?? true;
                    $href = $publicProfile
                        ? $mk('mockups.business', ['slug' => $sponsor['slug']])
                        : ($sponsor['website'] ?? null);
                @endphp
                <article class="ss-partner-card">
                    <div class="ss-partner-copy">
                        <h3>
                            @if ($href)
                                <a href="{{ $href }}" @if (! $publicProfile) target="_blank" rel="sponsored noopener noreferrer" @else rel="sponsored" @endif>{{ $sponsor['headline'] }}</a>
                            @else
                                {{ $sponsor['headline'] }}
                            @endif
                        </h3>
                        @if (($sponsor['body'] ?? '') !== '')
                            <p>{{ \Illuminate\Support\Str::limit($sponsor['body'], 160) }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <a class="ss-partner-open" href="{{ route('advertise') }}">
            <p class="ss-partner-label">Advertising</p>
            <p class="ss-partner-kicker">This space is available</p>
            <h3>Advertise here</h3>
            <p>A supplier placement on the match calendar. It stays labelled and does not change the order of events.</p>
            <span class="ss-partner-cta">Enquire about this space →</span>
        </a>
    @endif
</aside>
