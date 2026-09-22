<x-mockups.admin-layout title="Matches" active="matches">
    <header class="mk-pagehead">
        <h1>Matches</h1>
        <p class="mk-lede">{{ $matches->count() }} in this view. Actions do not write to the register.</p>
    </header>
    <form class="mk-filters" method="get">
        <label class="field grow"><span>Search</span><input type="search" name="q" value="{{ request('q') }}"></label>
        <label class="field">
            <span>Window</span>
            <select name="window">
                <option value="">All loaded</option>
                @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'draft' => 'Draft', 'published' => 'Published', 'missing' => 'Missing data'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('window') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Sport</span>
            <select name="sport">
                <option value="">Any</option>
                @foreach ($sports as $sport)
                    <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="field">
            <span>Province</span>
            <select name="province">
                <option value="">Any</option>
                @foreach ($provinces as $province)
                    <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn" type="submit">Filter</button>
    </form>
    <div class="mk-actions">
        <button class="btn ghost" type="button" disabled title="Mockup only">Publish selected</button>
        <a class="btn" href="{{ $mk('mockups.admin.matches.edit', ['new' => 1]) }}">Add match</a>
    </div>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Date</th>
                    <th>Match</th>
                    <th>Sport</th>
                    <th>Organiser</th>
                    <th>Range</th>
                    <th>Province</th>
                    <th>Status</th>
                    <th>Registration</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matches as $match)
                    <tr>
                        <td><input type="checkbox" aria-label="Select {{ $match['title'] }}"></td>
                        <td>{{ $match['date_label'] }}</td>
                        <td><a href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">{{ $match['title'] }}</a></td>
                        <td>{{ $match['discipline'] ?: '—' }}</td>
                        <td>{{ $match['organiser'] ?: '—' }}</td>
                        <td>{{ $match['range'] ?: '—' }}</td>
                        <td>{{ $match['province'] ?: '—' }}</td>
                        <td>{{ $match['status'] }}</td>
                        <td>{{ $match['entry_url'] ? 'Link' : 'None' }}</td>
                        <td>{{ $match['completeness']['percent'] }}%</td>
                        <td><a href="{{ $mk('mockups.admin.matches.edit', ['slug' => $match['slug']]) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11">No matches in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-mockups.admin-layout>
