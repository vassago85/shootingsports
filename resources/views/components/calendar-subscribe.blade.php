@props([
    'ics',
])

@php
    $webcal = (string) preg_replace('#^https?#', 'webcal', $ics);
    $google = 'https://calendar.google.com/calendar/render?cid='.rawurlencode($ics);
@endphp

<div class="embed-box">
    <p class="label">Subscribe on your phone</p>
    <p class="embed-help">Add this feed once. When you add or remove matches here, they appear on Google, Apple or Outlook the next time that calendar refreshes — usually within an hour.</p>
    <div class="embed-field" x-data="{ copied: false }">
        <span class="embed-field-label">Subscription URL</span>
        <div class="embed-field-row">
            <code class="embed-code" x-ref="code">{{ $ics }}</code>
            <button type="button" class="embed-copy" x-on:click="navigator.clipboard.writeText($refs.code.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 1600) })" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
        </div>
    </div>
    <p class="subscribe-actions">
        <a class="btn" href="{{ $google }}" rel="noopener noreferrer">Add to Google Calendar</a>
        <a class="btn ghost" href="{{ $webcal }}">Add to Apple Calendar</a>
    </p>
</div>
