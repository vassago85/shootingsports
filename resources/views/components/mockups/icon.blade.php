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
        @case('calendar')
            @if ($solid)
                <path fill-rule="evenodd" d="M8 2.2a1 1 0 0 1 1 1V4h6V3.2a1 1 0 1 1 2 0V4h1.6A2.4 2.4 0 0 1 21 6.4v12.2A2.4 2.4 0 0 1 18.6 21H5.4A2.4 2.4 0 0 1 3 18.6V6.4A2.4 2.4 0 0 1 5.4 4H7V3.2a1 1 0 0 1 1-1Zm-3.2 8.2v8.2c0 .2.2.4.6.4h13.2c.2 0 .4-.2.4-.4v-8.2H4.8Z"/>
            @else
                <rect x="3.5" y="5" width="17" height="15.5" rx="2"/>
                <path d="M8 3.2v3.2M16 3.2v3.2M3.5 10h17"/>
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
        @case('bag')
            <path d="M8.2 8V6.6A3.8 3.8 0 0 1 12 2.8a3.8 3.8 0 0 1 3.8 3.8V8"/>
            <path d="M5.6 8h12.8l-.7 11.1a1.8 1.8 0 0 1-1.8 1.7H8.1a1.8 1.8 0 0 1-1.8-1.7L5.6 8Z"/>
            @break
        @case('bell')
            <path d="M6.2 16.4h11.6l-1.15-1.9V10a4.65 4.65 0 0 0-9.3 0v4.5l-1.15 1.9Z"/>
            <path d="M10 16.4a2 2 0 0 0 4 0"/>
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
            <path d="M12 21s5.6-4.9 5.6-9.4a5.6 5.6 0 1 0-11.2 0C6.4 16.1 12 21 12 21Z"/>
            <circle cx="12" cy="11.4" r="1.8"/>
            @break
        @case('logout')
            <path d="M10 7.2V5.7A1.7 1.7 0 0 1 11.7 4h6.6A1.7 1.7 0 0 1 20 5.7v12.6a1.7 1.7 0 0 1-1.7 1.7h-6.6A1.7 1.7 0 0 1 10 18.3v-1.5"/>
            <path d="M4 12h10.2M11.2 9.1 14.2 12l-3 2.9"/>
            @break
        @default
    @endswitch
</svg>
