@props(['name'])
<svg class="v2-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('pin')
            <path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>
            @break
        @case('people')
            <circle cx="9" cy="8" r="3"/><path d="M3.5 19c.6-3 2.8-4.5 5.5-4.5S14 16 14.5 19"/><circle cx="17" cy="9" r="2.2"/><path d="M16 14.6c2.2.3 3.8 1.6 4.4 4.4"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8"/><path d="M12 8v4.5l3 2"/>
            @break
        @case('ruler')
            <path d="M4 16l12-12 4 4L8 20z"/><path d="M8 12l2 2M11 9l2 2M14 6l2 2"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>
            @break
        @case('list')
            <path d="M8 7h12M8 12h12M8 17h12"/><path d="M4 7h.01M4 12h.01M4 17h.01"/>
            @break
        @case('map')
            <path d="M9 18l-5 2V6l5-2 6 2 5-2v14l-5 2-6-2z"/><path d="M9 4v14M15 6v14"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5"/>
            @break
        @case('sort')
            <path d="M8 7h12M8 12h8M8 17h4"/><path d="M4 6v12M4 6l-2 2M4 6l2 2"/>
            @break
        @case('filter')
            <path d="M4 6h16l-6 7v5l-4 2v-7z"/>
            @break
        @case('ticket')
            <path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/>
            @break
    @endswitch
</svg>
