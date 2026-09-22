@php
    $match = $match ?? null;
    $missing = data_get($match, 'completeness.missing', ['Match name', 'Sport', 'Date', 'Range', 'Registration close']);
    $percent = data_get($match, 'completeness.percent', 0);
@endphp
<header class="mk-pagehead">
    <p class="label">{{ $asOrganiser ? 'List an event' : 'Match editor' }}</p>
    <h1>{{ $match['title'] ?? 'New match' }}</h1>
    <p class="mk-lede">This form does not save. It shows the structure of the editor.</p>
</header>
<form class="mk-form" id="match-editor" onsubmit="event.preventDefault(); document.getElementById('save-note').hidden = false;">
    <section>
        <h2>Basic</h2>
        <div class="grid">
            <label class="field"><span>Match name</span><input name="title" value="{{ $match['title'] ?? '' }}"></label>
            <label class="field">
                <span>Sport</span>
                <select name="sport">
                    <option value="">Select</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport['slug'] }}" @selected(($match['discipline_slug'] ?? null) === $sport['slug'])>{{ $sport['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Level</span>
                <select name="level">
                    @foreach (['club' => 'Club', 'series' => 'Series', 'provincial' => 'Provincial', 'national' => 'National', 'international' => 'International'] as $value => $label)
                        <option value="{{ $value }}" @selected(($match['level_value'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="field" style="margin-top:12px"><span>Description</span><textarea name="description">{{ $match['description'] ?? '' }}</textarea></label>
    </section>
    <section>
        <h2>When</h2>
        <div class="grid">
            <label class="field"><span>Date</span><input type="date" name="date" value="{{ $match['date'] ?? '' }}"></label>
            <label class="field"><span>Start</span><input name="time" value="{{ ($match['time'] ?? '') === 'All day' ? '' : ($match['time'] ?? '') }}" placeholder="08:00"></label>
            <label class="field"><span>End</span><input name="ends" value="{{ $match['ends_at'] ?? '' }}"></label>
            <label class="field"><span>Registration close</span><input name="closes" placeholder="Not stored yet" disabled></label>
        </div>
    </section>
    <section>
        <h2>Where</h2>
        <div class="grid">
            <label class="field">
                <span>Range</span>
                <select name="range">
                    <option value="">Select</option>
                    @foreach ($ranges as $range)
                        <option value="{{ $range['slug'] }}" @selected(($match['range_slug'] ?? null) === $range['slug'])>{{ $range['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span>Town / province</span><input value="{{ collect([$match['town'] ?? null, $match['province'] ?? null])->filter()->implode(', ') }}" disabled></label>
        </div>
    </section>
    <section>
        <h2>Match details</h2>
        <div class="grid">
            <label class="field"><span>Round count</span><input name="rounds" value="{{ $match['rounds'] ?? '' }}"></label>
            <label class="field"><span>Distance</span><input name="distance" value="{{ $match['distance'] ?? '' }}"></label>
            <label class="field"><span>Entry fee (cents)</span><input name="fee" value="{{ $match['fee_cents'] ?? '' }}"></label>
            <label class="mk-check"><input type="checkbox" @checked(in_array('new-shooter-friendly', data_get($match, 'flag_slugs', []), true))> Beginner friendly</label>
        </div>
        <label class="field" style="margin-top:12px"><span>Equipment</span><textarea>{{ $match['equipment'] ?? '' }}</textarea></label>
    </section>
    <section>
        <h2>Registration</h2>
        <label class="field"><span>External entry URL</span><input name="entry_url" value="{{ $match['entry_url'] ?? '' }}"></label>
    </section>
    <section>
        <h2>Organiser</h2>
        <label class="field">
            <span>Club or series</span>
            <select>
                <option value="">Select</option>
                @foreach ($clubs as $club)
                    <option value="{{ $club['slug'] }}" @selected(($match['organiser_slug'] ?? null) === $club['slug'])>{{ $club['name'] }}</option>
                @endforeach
            </select>
        </label>
    </section>
    @unless ($asOrganiser)
        <section>
            <h2>Media</h2>
            <p class="mk-support">Banner upload stays on the existing editor. This mockup does not add a second uploader.</p>
        </section>
        <section>
            <h2>SEO</h2>
            <p class="mk-support">Public titles are generated from the match name, sport and place. No separate SEO fields are stored today.</p>
        </section>
        <section>
            <h2>Status</h2>
            <p>{{ $match['status'] ?? 'Draft' }}</p>
        </section>
    @endunless
    <div class="mk-savebar">
        <div class="mk-meter">{{ $percent }}% complete <span>Missing: {{ $missing === [] ? 'None' : implode(', ', $missing) }}</span></div>
        <button class="btn ghost" type="submit">Draft</button>
        @if ($match)
            <a class="btn ghost" href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">Preview</a>
        @endif
        <button class="btn" type="submit">{{ $match ? 'Update' : 'Publish' }}</button>
        <span id="save-note" hidden>Mockup only. Nothing was written to the register.</span>
    </div>
</form>
