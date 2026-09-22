<x-mockups.admin-layout title="Ranges" active="ranges">
    <header class="mk-pagehead">
        <h1>Ranges</h1>
    </header>
    <form class="mk-filters" method="get">
        <label class="field">
            <span>Show</span>
            <select name="filter" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="gps" @selected(request('filter') === 'gps')>Missing GPS</option>
                <option value="distance" @selected(request('filter') === 'distance')>Missing max distance</option>
                <option value="club" @selected(request('filter') === 'club')>No associated club</option>
            </select>
        </label>
        <a class="mk-textlink" href="{{ $mk('mockups.admin.duplicates') }}">Possible duplicates</a>
    </form>
    <aside class="mk-internal"><span>Reviewer note</span><p>Ranges have no phone or email column, so “missing contact” is a schema gap rather than a per-row filter.</p></aside>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr><th>Range</th><th>Province</th><th>GPS</th><th>Max distance</th><th>Disciplines</th><th>Clubs</th><th>Upcoming</th></tr>
            </thead>
            <tbody>
                @foreach ($ranges as $range)
                    <tr>
                        <td><a href="{{ $mk('mockups.range', ['slug' => $range['slug']]) }}">{{ $range['name'] }}</a></td>
                        <td>{{ $range['province'] ?: '—' }}</td>
                        <td>{{ $range['has_gps'] ? 'Yes' : 'Missing' }}</td>
                        <td>{{ $range['max_distance'] ?: 'Missing' }}</td>
                        <td>{{ count($range['disciplines']) }}</td>
                        <td>{{ count($range['clubs']) }}</td>
                        <td>{{ $range['upcoming_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-mockups.admin-layout>
