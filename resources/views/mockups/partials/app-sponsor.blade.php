@if (filled($sponsor['image'] ?? null))
    @php
        $publicProfile = $sponsor['public'] ?? true;
        $href = $publicProfile
            ? $app('supplier', ['supplier' => $sponsor['slug']])
            : ($sponsor['website'] ?? null);
        $alt = $sponsor['headline'] ?: ($sponsor['name'] ?? 'Sponsored');
    @endphp
    <figure class="app-ad">
        <figcaption class="app-ad-label">Sponsored</figcaption>
        @if ($href)
            <a href="{{ $href }}" @if ($publicProfile) rel="sponsored" @else target="_blank" rel="sponsored noopener noreferrer" @endif>
                <img src="{{ $sponsor['image'] }}" alt="{{ $alt }}">
            </a>
        @else
            <img src="{{ $sponsor['image'] }}" alt="{{ $alt }}">
        @endif
    </figure>
@endif
