<div class="app-views" role="group" aria-label="Match view">
    <a href="{{ $app('today', ['month' => null, 'day' => null]) }}" @class(['on' => $screen === 'today'])>List</a>
    <a href="{{ $app('calendar', ['day' => null]) }}" @class(['on' => $screen === 'calendar'])>
        <x-mockups.icon name="calendar" />
        Calendar
    </a>
</div>
