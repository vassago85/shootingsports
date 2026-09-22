<x-mockups.layout title="Club matches" active="account">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Club desk</p>
            <h1>{{ $club['name'] ?? 'No club' }} matches</h1>
            <p class="mk-lede">{{ $matches->count() }} in this view. Actions do not write to the register.</p>
        </header>

        @include('mockups.partials.manage-nav', ['active' => 'matches', 'club' => $club])

        @if ($club === null)
            <p>No clubs available in this preview.</p>
        @else
            <form class="mk-filters" method="get">
                <input type="hidden" name="club" value="{{ $club['slug'] }}">
                <label class="field">
                    <span>Window</span>
                    <select name="window" onchange="this.form.submit()">
                        <option value="">Upcoming</option>
                        @foreach (['past' => 'Past', 'draft' => 'Drafts', 'missing' => 'Missing data'] as $value => $label)
                            <option value="{{ $value }}" @selected($window === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <a class="btn" href="{{ $mk('mockups.manage.matches.new', ['club' => $club['slug']]) }}">Create match</a>
            </form>

            <div class="mk-table-wrap" style="margin-top:16px">
                <table class="mk-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Match</th>
                            <th>Sport</th>
                            <th>Range</th>
                            <th>Status</th>
                            <th>Registration</th>
                            <th>Complete</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matches as $match)
                            <tr>
                                <td>{{ $match['date_label'] }}</td>
                                <td><a href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">{{ $match['title'] }}</a></td>
                                <td>{{ $match['discipline'] ?: '—' }}</td>
                                <td>{{ $match['range'] ?: '—' }}</td>
                                <td>{{ $match['status'] }}</td>
                                <td>{{ $match['entry_url'] ? 'Link' : 'None' }}</td>
                                <td>{{ $match['completeness']['percent'] ?? 100 }}%</td>
                                <td><a class="mk-textlink" href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">Preview</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No matches in this view. <a class="mk-textlink" href="{{ $mk('mockups.manage.matches.new', ['club' => $club['slug']]) }}">Create the first one</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-mockups.layout>
