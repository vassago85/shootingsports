<x-mockups.admin-layout title="Clubs" active="clubs">
    <header class="mk-pagehead">
        <h1>Clubs</h1>
        <p class="mk-lede">{{ $clubs->count() }} listings in this view.</p>
    </header>
    @if (request('new') === '1')
        <form class="mk-form" onsubmit="event.preventDefault(); this.querySelector('[data-note]').hidden = false;">
            <section>
                <h2>New club</h2>
                <div class="grid">
                    <label class="field"><span>Name</span><input></label>
                    <label class="field"><span>Province</span><input></label>
                </div>
                <button class="btn" type="submit" style="margin-top:12px">Save draft</button>
                <span data-note hidden>Mockup only. Nothing was written.</span>
            </section>
        </form>
    @endif
    <form class="mk-filters" method="get">
        <label class="field">
            <span>Show</span>
            <select name="filter" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach (['incomplete' => 'Incomplete', 'stale' => 'Stale', 'unverified' => 'Unverified', 'quiet' => 'No upcoming matches'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('filter') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr><th>Club</th><th>Province</th><th>Sports</th><th>Upcoming</th><th>Verification</th><th>Updated</th></tr>
            </thead>
            <tbody>
                @forelse ($clubs as $club)
                    <tr>
                        <td><a href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">{{ $club['name'] }}</a></td>
                        <td>{{ $club['province'] ?: '—' }}</td>
                        <td>{{ $club['disciplines'] === [] ? '—' : count($club['disciplines']) }}</td>
                        <td>{{ $club['upcoming_count'] }}</td>
                        <td>{{ $club['verification'] ?: '—' }}</td>
                        <td>{{ $club['updated'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No clubs in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-mockups.admin-layout>
