@props([
    'club' => null,
    'organisation' => null,
    'venue' => null,
    'shooter' => null,
])

@php
    $query = array_filter([
        'club' => $club,
        'organisation' => $organisation,
        'venue' => $venue,
        'shooter' => $shooter,
    ]);
    $calendarUrl = route('embed.calendar', $query);
    $iframe = '<iframe src="'.e($calendarUrl).'" title="Shooting Sports match calendar" width="800" height="520" frameborder="0" scrolling="no" style="width:100%;min-height:520px;border:0;"></iframe>';
@endphp

<div class="embed-box">
    <p class="label">Embed this calendar</p>
    @auth
        <p class="embed-help">Put these matches on your own site. On WordPress, paste the URL on its own line. In Elementor or a Custom HTML block, paste the iframe. Theme options are on the <a href="{{ route('embed.docs') }}">embed docs</a>.</p>
        <div class="embed-field" x-data="{ copied: false }">
            <span class="embed-field-label">WordPress URL</span>
            <div class="embed-field-row">
                <code class="embed-code" x-ref="code">{{ $calendarUrl }}</code>
                <button type="button" class="embed-copy" x-on:click="navigator.clipboard.writeText($refs.code.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1600) })" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
        </div>
        <div class="embed-field" x-data="{ copied: false }">
            <span class="embed-field-label">Iframe</span>
            <div class="embed-field-row">
                <code class="embed-code" x-ref="code">{{ $iframe }}</code>
                <button type="button" class="embed-copy" x-on:click="navigator.clipboard.writeText($refs.code.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1600) })" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
        </div>
    @else
        <p class="embed-help">Sign in to embed this calendar on your own site.</p>
        <a class="btn" href="{{ url('/desk/login') }}">Director login</a>
    @endauth
</div>
