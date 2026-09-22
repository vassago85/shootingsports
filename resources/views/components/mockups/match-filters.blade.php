@props(['sports', 'provinces'])
<form method="get" action="{{ url()->current() }}" data-finder>
    @if (request('device') === 'mobile')
        <input type="hidden" name="device" value="mobile">
    @endif
    @if (request()->routeIs('mockups.matches.calendar') && request('month'))
        <input type="hidden" name="month" value="{{ request('month') }}">
    @endif
    @if (request('lat'))
        <input type="hidden" name="lat" value="{{ request('lat') }}">
        <input type="hidden" name="lng" value="{{ request('lng') }}">
    @endif
    <div class="mk-filters">
        <label class="field">
            <span>Sport</span>
            <select name="sport">
                <option value="">Any sport</option>
                @foreach ($sports as $sport)
                    <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Province / near me</span>
            <select name="province">
                <option value="">Any province</option>
                <option value="near">Near me</option>
                @foreach ($provinces as $province)
                    <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>From</span>
            <input type="date" name="from" value="{{ request('from') }}">
        </label>
        <label class="field">
            <span>To</span>
            <input type="date" name="to" value="{{ request('to') }}">
        </label>
        <label class="field">
            <span>Sort</span>
            <select name="sort">
                <option value="soonest" @selected(request('sort', 'soonest') === 'soonest')>Soonest</option>
                <option value="closest" @selected(request('sort') === 'closest')>Closest</option>
                <option value="recent" @selected(request('sort') === 'recent')>Recently added</option>
            </select>
        </label>
        <button class="btn" type="submit">Find matches</button>
    </div>
    <details class="mk-advanced" @if(request()->hasAny(['level', 'beginner', 'fee', 'confirmed', 'radius'])) open @endif>
        <summary>More filters</summary>
        <div class="mk-filters">
            <label class="field">
                <span>Match level</span>
                <select name="level">
                    <option value="">Any level</option>
                    @foreach (['club' => 'Club', 'series' => 'Series', 'provincial' => 'Provincial', 'national' => 'National', 'international' => 'International'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('level') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Entry fee</span>
                <select name="fee">
                    <option value="">Any</option>
                    <option value="listed" @selected(request('fee') === 'listed')>Fee published</option>
                </select>
            </label>
            <label class="field">
                <span>Distance from me</span>
                <select name="radius">
                    <option value="">Any distance</option>
                    @foreach ([50, 100, 150, 300] as $km)
                        <option value="{{ $km }}" @selected((string) request('radius') === (string) $km)>Within {{ $km }} km</option>
                    @endforeach
                </select>
            </label>
            <label class="mk-check"><input type="checkbox" name="confirmed" value="1" @checked(request('confirmed') === '1')> Confirmed events only</label>
            <label class="mk-check"><input type="checkbox" name="beginner" value="1" @checked(request('beginner') === '1')> New shooter friendly</label>
        </div>
    </details>
</form>
