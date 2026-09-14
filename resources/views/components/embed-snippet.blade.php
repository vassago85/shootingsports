@props([
    'club' => null,
    'organisation' => null,
    'venue' => null,
])

@php
    $query = array_filter([
        'club' => $club,
        'organisation' => $organisation,
        'venue' => $venue,
    ]);
    $calendarUrl = route('embed.calendar', $query);
    $iframe = '<iframe src="'.e($calendarUrl).'" title="Shooting Sports match calendar" width="800" height="520" frameborder="0" scrolling="no" style="width:100%;min-height:520px;border:0;"></iframe>';
@endphp

<div class="embed-box">
    <p class="label">Embed this calendar</p>
    @auth
        <p class="meta" style="margin:0 0 10px;font-family:var(--f-mono);font-size:13px;color:var(--slate)">Put these matches on your own site. WordPress: paste the URL on its own line. Other builders: paste the iframe into a Custom HTML / Elementor widget. Theme options are on the <a href="{{ route('embed.docs') }}">embed docs</a>.</p>
        <div class="embed-row" x-data="{ copied: false }">
            <pre class="mono embed-code" x-ref="code">{{ $calendarUrl }}</pre>
            <button type="button" class="btn ghost" x-on:click="navigator.clipboard.writeText($refs.code.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1600) })" x-text="copied ? 'Copied' : 'Copy WordPress URL'">Copy WordPress URL</button>
        </div>
        <div class="embed-row" x-data="{ copied: false }">
            <pre class="mono embed-code" x-ref="code">{{ $iframe }}</pre>
            <button type="button" class="btn ghost" x-on:click="navigator.clipboard.writeText($refs.code.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1600) })" x-text="copied ? 'Copied' : 'Copy iframe'">Copy iframe</button>
        </div>
    @else
        <p class="meta" style="margin:0 0 12px;font-family:var(--f-mono);font-size:13px;color:var(--slate)">Sign in to embed this calendar on your club, range or federation site.</p>
        <a class="btn" href="{{ url('/desk/login') }}">Director login</a>
    @endauth
</div>
