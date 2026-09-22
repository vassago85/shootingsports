@props(['name', 'solid' => false])

@php
    $solid = filter_var($solid, FILTER_VALIDATE_BOOLEAN);
@endphp

<svg {{ $attributes->class('app-icon') }} viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"
    @if ($solid)
        fill="currentColor"
    @else
        fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
    @endif
>
    @switch($name)
        @case('home')
            @if ($solid)
                <path d="M11.3 2.6a1 1 0 0 1 1.4 0l8.4 7.8c.4.4.1 1.2-.5 1.2H19v7.9c0 1-.8 1.7-1.7 1.7h-3.1v-6.6h-4.4v6.6H6.7c-1 0-1.7-.8-1.7-1.7v-7.9H3.4c-.6 0-.9-.8-.5-1.2l8.4-7.8Z"/>
            @else
                <path d="M3.5 11.2 12 3.6l8.5 7.6"/>
                <path d="M5.5 10v9.1a1.4 1.4 0 0 0 1.4 1.4H10v-6h4v6h3.1a1.4 1.4 0 0 0 1.4-1.4V10"/>
            @endif
            @break
        @case('calendar')
            @if ($solid)
                <path fill-rule="evenodd" d="M8 2.2a1 1 0 0 1 1 1V4h6V3.2a1 1 0 1 1 2 0V4h1.6A2.4 2.4 0 0 1 21 6.4v12.2A2.4 2.4 0 0 1 18.6 21H5.4A2.4 2.4 0 0 1 3 18.6V6.4A2.4 2.4 0 0 1 5.4 4H7V3.2a1 1 0 0 1 1-1Zm-3.2 8.2v8.2c0 .2.2.4.6.4h13.2c.2 0 .4-.2.4-.4v-8.2H4.8Z"/>
            @else
                <rect x="3.5" y="5" width="17" height="15.5" rx="2"/>
                <path d="M8 3.2v3.2M16 3.2v3.2M3.5 10h17"/>
            @endif
            @break
        @case('list')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('map')
            <path d="M9 3.5 3.5 5.5v14L9 17.5m0-14 6 2m-6-2v14m6-12 5.5-2v14L15 19.5m0-14v14"/>
            @break
        @case('compass')
            @if ($solid)
                <path fill-rule="evenodd" d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm3.6 5.5-1.5 4.8-4.8 1.5 1.5-4.8 4.8-1.5Z"/>
            @else
                <circle cx="12" cy="12" r="8.5"/>
                <path d="m14.5 9.5-1.5 4.5-4.5 1.5 1.5-4.5 4.5-1.5Z"/>
            @endif
            @break
        @case('search')
            @if ($solid)
                <path fill-rule="evenodd" d="M11 3a8 8 0 1 0 5.05 14.2l3.35 3.35a1.15 1.15 0 0 0 1.63-1.63l-3.35-3.35A8 8 0 0 0 11 3Zm0 2.4a5.6 5.6 0 1 1 0 11.2 5.6 5.6 0 0 1 0-11.2Z"/>
            @else
                <circle cx="11" cy="11" r="6.25"/>
                <path d="m16 16.2 4.2 4.2"/>
            @endif
            @break
        @case('store')
            @if ($solid)
                <path d="M4.15 9.15 6.2 4.4A1.3 1.3 0 0 1 7.38 3.6h9.24c.48 0 .92.26 1.15.68l2.08 4.87c.18.42.05.9-.32 1.18-.22.16-.5.25-.77.25h-.26v8.17c0 .8-.65 1.45-1.45 1.45H15v-5.4h-6v5.4H6.95c-.8 0-1.45-.65-1.45-1.45V10.58h-.26c-.28 0-.55-.09-.77-.25a1.05 1.05 0 0 1-.32-1.18Z"/>
            @else
                <path d="M4 9.4 6.1 4.6h11.8L20 9.4"/>
                <path d="M4 9.4h16V19a1.4 1.4 0 0 1-1.4 1.4H5.4A1.4 1.4 0 0 1 4 19V9.4Z"/>
                <path d="M9.5 20.4v-5.6h5v5.6"/>
            @endif
            @break
        @case('user')
            @if ($solid)
                <circle cx="12" cy="8" r="3.4"/>
                <path d="M5.1 19.6c1.2-3.3 3.6-4.8 6.9-4.8s5.7 1.5 6.9 4.8c.2.55-.22 1.15-.82 1.15H5.92c-.6 0-1.02-.6-.82-1.15Z"/>
            @else
                <circle cx="12" cy="8" r="3.15"/>
                <path d="M5.6 19.4c1.15-2.9 3.4-4.3 6.4-4.3s5.25 1.4 6.4 4.3"/>
            @endif
            @break
        @case('users')
            <circle cx="9" cy="8.2" r="2.35"/>
            <circle cx="16" cy="9" r="1.9"/>
            <path d="M4.6 18.4c.75-2.3 2.45-3.45 4.4-3.45 1.95 0 3.6 1.15 4.4 3.45"/>
            <path d="M13.2 14.9c1.05-.35 2.15-.25 3.15.4 1.05.85 1.6 2 1.9 3.1"/>
            @break
        @case('flag')
            <path d="M6 4.2h9.2L13 7.6l2.2 3.4H6V4.2Z"/>
            <path d="M6 4.2v15.4"/>
            @break
        @case('badge')
            <circle cx="12" cy="8.5" r="3.4"/>
            <path d="M8.4 12.2 7 20.2 12 17.8l5 2.4-1.4-8"/>
            @break
        @case('range')
            <circle cx="12" cy="12" r="6.5"/>
            <path d="M12 2.8v3.1M12 18.1v3.1M2.8 12h3.1M18.1 12h3.1"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="7.5"/>
            <circle cx="12" cy="12" r="4"/>
            <circle cx="12" cy="12" r="1.15" fill="currentColor" stroke="none"/>
            @break
        @case('rifle')
            <path d="M3 15.5 15 3.5l3 3-12 12H3v-3Z"/>
            <path d="M13.5 5 19 10.5"/>
            @break
        @case('handgun')
            <path d="M4 8h14v3l-2 2h-3l-2 4H8l-1-4H4V8Z"/>
            <path d="M4 5h6v3"/>
            @break
        @case('shotgun')
            <path d="M3 12h13l4-4v3l-4 4H3v-3Z"/>
            <path d="M3 12v3"/>
            @break
        @case('airgun')
            <circle cx="7" cy="12" r="3.5"/>
            <path d="M10 12h11"/>
            <path d="M18 9v6"/>
            @break
        @case('multi')
            <path d="M3 6.5h11l3 3v3l-3 3H3v-9Z"/>
            <path d="M17 12h4"/>
            @break
        @case('bag')
            <path d="M8.2 8V6.6A3.8 3.8 0 0 1 12 2.8a3.8 3.8 0 0 1 3.8 3.8V8"/>
            <path d="M5.6 8h12.8l-.7 11.1a1.8 1.8 0 0 1-1.8 1.7H8.1a1.8 1.8 0 0 1-1.8-1.7L5.6 8Z"/>
            @break
        @case('box')
            <path d="M3.5 7.4 12 3.5l8.5 3.9v9.2L12 20.5l-8.5-3.9V7.4Z"/>
            <path d="M3.5 7.4 12 11.4l8.5-4M12 11.4v9.1"/>
            @break
        @case('bell')
            @if ($solid)
                <path d="M6.2 16.4h11.6l-1.15-1.9V10a4.65 4.65 0 0 0-9.3 0v4.5l-1.15 1.9Z"/>
                <path d="M10 18.4a2 2 0 0 0 4 0"/>
            @else
                <path d="M6.2 16.4h11.6l-1.15-1.9V10a4.65 4.65 0 0 0-9.3 0v4.5l-1.15 1.9Z"/>
                <path d="M10 16.4a2 2 0 0 0 4 0"/>
            @endif
            @break
        @case('bookmark')
            @if ($solid)
                <path d="M6.4 3.4h11.2a1 1 0 0 1 1 1v16.2L12 17.4l-6.6 3.2V4.4a1 1 0 0 1 1-1Z"/>
            @else
                <path d="M6.4 3.4h11.2a1 1 0 0 1 1 1v16.2L12 17.4l-6.6 3.2V4.4a1 1 0 0 1 1-1Z"/>
            @endif
            @break
        @case('share')
            <path d="M15 8.5V4L21 10l-6 6V11.5"/>
            <path d="M15 11.5c-6 0-8 3.5-8 8 0-3.5 1-6 8-6"/>
            @break
        @case('shield')
            <path d="M12 3.4 19 6.1v5.2c0 4-2.7 6.5-7 8.2-4.3-1.7-7-4.2-7-8.2V6.1L12 3.4Z"/>
            @break
        @case('card')
            <rect x="3.2" y="6" width="17.6" height="12" rx="2"/>
            <path d="M3.2 10h17.6M7 14.2h4"/>
            @break
        @case('lock')
            <rect x="5.8" y="10.6" width="12.4" height="9" rx="2"/>
            <path d="M8.4 10.6V8.2a3.6 3.6 0 0 1 7.2 0v2.4"/>
            @break
        @case('file')
            <path d="M7 3.4h6.6L18.2 8v12.2a1.4 1.4 0 0 1-1.4 1.4H7.2A1.4 1.4 0 0 1 5.8 20.2V4.8A1.4 1.4 0 0 1 7.2 3.4H7Z"/>
            <path d="M13.4 3.6V8h4.6"/>
            @break
        @case('pin')
            @if ($solid)
                <path d="M12 21s5.6-4.9 5.6-9.4a5.6 5.6 0 1 0-11.2 0C6.4 16.1 12 21 12 21Z"/>
                <circle cx="12" cy="11.4" r="1.8" fill="#fff" stroke="none"/>
            @else
                <path d="M12 21s5.6-4.9 5.6-9.4a5.6 5.6 0 1 0-11.2 0C6.4 16.1 12 21 12 21Z"/>
                <circle cx="12" cy="11.4" r="1.8"/>
            @endif
            @break
        @case('logout')
            <path d="M10 7.2V5.7A1.7 1.7 0 0 1 11.7 4h6.6A1.7 1.7 0 0 1 20 5.7v12.6a1.7 1.7 0 0 1-1.7 1.7h-6.6A1.7 1.7 0 0 1 10 18.3v-1.5"/>
            <path d="M4 12h10.2M11.2 9.1 14.2 12l-3 2.9"/>
            @break
        @case('back')
            <path d="M10 4 3 12l7 8"/>
            <path d="M3 12h18"/>
            @break
        @case('close')
            <path d="M6 6l12 12M18 6L6 18"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('check')
            <path d="M4.5 12.5 9 17l10.5-10.5"/>
            @break
        @case('check-circle')
            @if ($solid)
                <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.4 14L5.9 11.3l1.6-1.6 3.1 3.1 5.7-5.7 1.6 1.6L10.6 16Z"/>
            @else
                <circle cx="12" cy="12" r="9"/>
                <path d="m8 12.5 3 3 5.5-6"/>
            @endif
            @break
        @case('chevron')
        @case('chev')
            <path d="m9 6 6 6-6 6"/>
            @break
        @case('chev-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('chev-up')
            <path d="m6 15 6-6 6 6"/>
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 5l7 7-7 7"/>
            @break
        @case('external')
            <path d="M14 4h6v6"/>
            <path d="M20 4 10 14"/>
            <path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>
            @break
        @case('phone')
            <path d="M5 3.5h3.2l1.4 4.4-2 1.4c1 2 2.6 3.6 4.6 4.6l1.4-2 4.4 1.4V16a2 2 0 0 1-2 2h-1A13 13 0 0 1 4 6V5a1.5 1.5 0 0 1 1-1.5Z"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>
            @break
        @case('link')
            <path d="M10 14a5 5 0 0 0 7.1 0l3-3a5 5 0 0 0-7.1-7.1l-1.4 1.4"/>
            <path d="M14 10a5 5 0 0 0-7.1 0l-3 3a5 5 0 0 0 7.1 7.1l1.4-1.4"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/>
            @break
        @case('moon')
            <path d="M20 15.4A8 8 0 1 1 8.6 4a6.5 6.5 0 0 0 11.4 11.4Z"/>
            @break
        @case('sparkle')
            <path d="M12 3v6M12 15v6M3 12h6M15 12h6"/>
            <path d="m6 6 3.5 3.5M14.5 14.5 18 18M6 18l3.5-3.5M14.5 9.5 18 6"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 8v.1M12 11v5"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M10 11v6M14 11v6"/>
            @break
        @case('gear')
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5v.3a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7.2 4.5l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1h.3a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.5 1Z"/>
            @break
        @default
    @endswitch
</svg>
