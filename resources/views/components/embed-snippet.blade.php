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
    <p class="meta" style="margin:0 0 10px;font-family:var(--f-mono);font-size:13px;color:var(--slate)">WordPress: paste the URL on its own line, or drop the iframe into a Custom HTML / Elementor HTML widget. Options are on the <a href="{{ route('embed.docs') }}">embed docs</a>.</p>
    <pre class="mono embed-code">{{ $calendarUrl }}</pre>
    <pre class="mono embed-code" style="margin-top:10px">{{ $iframe }}</pre>
</div>
