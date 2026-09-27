@php
    /*
     * Mobile drawer nav. Same items as x-public-nav (desktop) via
     * App\Support\PublicNav — hubs expand flat here (no popover)
     * with a small header row so the grouping stays obvious.
     */
    $items = \App\Support\PublicNav::items();
@endphp
<nav class="mobile-menu" id="mobile-menu" aria-label="Mobile">
    @foreach ($items as $item)
        @php $active = \App\Support\PublicNav::isActive($item); @endphp
        @if (empty($item['children']))
            <a
                href="{{ route($item['route']) }}"
                class="{{ $active ? 'on' : '' }}"
                @if ($active) aria-current="page" @endif
            >{{ $item['label'] }}</a>
        @else
            <div class="mobile-hub">
                <span class="mobile-hub-label">{{ $item['label'] }}</span>
                @foreach ($item['children'] as $child)
                    @php $childActive = \App\Support\PublicNav::isActive($child); @endphp
                    <a
                        href="{{ route($child['route']) }}"
                        class="mobile-hub-item {{ $childActive ? 'on' : '' }}"
                        @if ($childActive) aria-current="page" @endif
                    >{{ $child['label'] }}</a>
                @endforeach
            </div>
        @endif
    @endforeach

    <a href="{{ route('calendar.month') }}" class="{{ request()->routeIs('calendar.month') ? 'on' : '' }}">Calendar</a>
    <a href="{{ route('feed') }}" class="{{ request()->routeIs('feed') ? 'on' : '' }}">Activity</a>
    <a href="{{ route('claim') }}">For clubs</a>

    @auth
        <a href="{{ route('my-calendar') }}">My calendar</a>
        <a href="{{ route('my-log') }}">My log</a>
        <a href="{{ route('settings.notifications') }}">Notifications</a>
        @if (auth()->user()?->is_staff || auth()->user()?->is_match_director || auth()->user()?->is_media_partner)
            <a href="{{ url('/desk') }}">Desk</a>
        @endif
        @unless (auth()->user()->is_staff || auth()->user()->is_match_director)
            <a href="{{ route('events.submit') }}">Submit an event</a>
        @endunless
        @if (auth()->user()?->isSupplier())
            <a href="{{ route('suppliers.onboard') }}">My business</a>
        @else
            <a href="{{ route('suppliers.start') }}">List your business</a>
        @endif
    @else
        <a href="{{ route('login') }}">Sign in</a>
        <a href="{{ route('register') }}">Create an account</a>
    @endauth
    <a href="{{ route('advertise') }}">Advertise</a>
</nav>
