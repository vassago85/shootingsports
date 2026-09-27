@props([
    'title' => 'For administrators',
])

{{--
    Bottom-of-page block for owner / director / operator tools.
    Keeps claim buttons, embed snippets and "Manage this listing"
    controls together and out of the shooter-facing area above.
    Body is projected via the default slot — callers own their
    content (buttons, embed snippet, links).
--}}
<aside class="owner-panel" aria-label="{{ $title }}">
    <div class="owner-panel-head">
        <h2>{{ $title }}</h2>
    </div>
    <div class="owner-panel-body">
        {{ $slot }}
    </div>
</aside>
