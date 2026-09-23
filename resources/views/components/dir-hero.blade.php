@props(['page', 'kicker', 'title'])

@php
    $url = \App\Support\PageHeroes::url($page);
    $image = filled($url) ? "--hero-image: url('".str_replace(['\\', "'"], ['/', ''], $url)."')" : null;
@endphp

<header {{ $attributes->class('dir-hero') }}>
    <div class="dir-hero-copy">
        <p class="label">{{ $kicker }}</p>
        <h1>{{ $title }}</h1>
        @if (trim($slot) !== '')
            <div class="lede">{{ $slot }}</div>
        @endif
    </div>
    <div class="dir-hero-art" @if ($image) style="{{ $image }}" @endif @if (filled($url)) role="img" aria-label="" @endif></div>
</header>
