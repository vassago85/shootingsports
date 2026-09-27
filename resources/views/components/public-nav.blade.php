@php
    /*
     * Desktop primary nav. Shares its items() with the mobile drawer
     * (x-public-nav-mobile) via App\Support\PublicNav so both surfaces
     * agree on labels, routes and active-state patterns.
     */
    $items = \App\Support\PublicNav::items();
@endphp
<nav class="nav-links" aria-label="Primary">
    @foreach ($items as $item)
        @php $active = \App\Support\PublicNav::isActive($item); @endphp
        @if (empty($item['children']))
            <a
                href="{{ route($item['route']) }}"
                class="{{ $active ? 'on' : '' }}"
                @if ($active) aria-current="page" @endif
            >{{ $item['label'] }}</a>
        @else
            <div
                class="nav-hub"
                x-data="{ open: false }"
                @mouseenter="open = true"
                @mouseleave="open = false"
                @focusin="open = true"
                @focusout="if (! $event.currentTarget.contains($event.relatedTarget)) { open = false }"
                @keydown.escape.window="open = false"
            >
                <button
                    type="button"
                    class="nav-hub-toggle {{ $active ? 'on' : '' }}"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="true"
                    @click="open = ! open"
                    @if ($active) aria-current="section" @endif
                >
                    {{ $item['label'] }}
                    <svg class="nav-hub-caret" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true">
                        <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div
                    class="nav-hub-panel"
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    role="menu"
                >
                    @foreach ($item['children'] as $child)
                        @php $childActive = \App\Support\PublicNav::isActive($child); @endphp
                        <a
                            href="{{ route($child['route']) }}"
                            role="menuitem"
                            class="{{ $childActive ? 'on' : '' }}"
                            @if ($childActive) aria-current="page" @endif
                        >{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
