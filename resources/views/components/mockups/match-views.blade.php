@php
    $query = request()->except(['page']);
@endphp
<nav class="mk-views" aria-label="Match views">
    <a href="{{ $mk('mockups.matches', $query) }}" @class(['on' => request()->routeIs('mockups.matches')])>List</a>
    <a href="{{ $mk('mockups.matches.calendar', $query) }}" @class(['on' => request()->routeIs('mockups.matches.calendar')])>Calendar</a>
    <a href="{{ $mk('mockups.matches.map', $query) }}" @class(['on' => request()->routeIs('mockups.matches.map')])>Map</a>
</nav>
