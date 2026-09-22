<form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
    <input type="hidden" name="screen" value="search">
    <input type="hidden" name="theme" value="{{ request()->query('theme') === 'light' ? 'light' : 'dark' }}">
    @if (request()->query('type') === 'large')
        <input type="hidden" name="type" value="large">
    @endif
    @if (request()->query('device') === 'mobile')
        <input type="hidden" name="device" value="mobile">
    @endif
    @if (($scope ?? null) === 'all')
        <input type="hidden" name="scope" value="all">
    @endif
    @foreach ($dropped ?? [] as $key)
        <input type="hidden" name="drop[]" value="{{ $key }}">
    @endforeach
    @if (request()->filled('home'))
        <input type="hidden" name="home" value="{{ $close['home'] }}">
    @endif
    @if (($close['anywhere'] ?? false) === true)
        <input type="hidden" name="province" value="all">
    @elseif (($close['using_home'] ?? false) !== true && filled($close['province'] ?? null))
        <input type="hidden" name="province" value="{{ $close['province'] }}">
    @endif
    @if (($close['gps'] ?? false) === true)
        <input type="hidden" name="near" value="1">
        <input type="hidden" name="lat" value="{{ $close['lat'] }}">
        <input type="hidden" name="lng" value="{{ $close['lng'] }}">
    @endif
    @if (($close['on'] ?? false) === true)
        <input type="hidden" name="km" value="{{ $close['km'] }}">
    @endif
    <label class="app-search-field">
        <x-mockups.icon name="search" />
        <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Search matches" aria-label="Search matches">
    </label>
</form>
