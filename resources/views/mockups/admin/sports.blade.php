<x-mockups.admin-layout title="Sports" active="sports">
    <header class="mk-pagehead">
        <h1>Sports</h1>
        <p class="mk-lede">Which discipline pages are thin.</p>
    </header>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr><th>Sport</th><th>Category</th><th>Description</th><th>Clubs</th><th>Ranges</th><th>Upcoming</th></tr>
            </thead>
            <tbody>
                @foreach ($sports as $sport)
                    <tr>
                        <td><a href="{{ $mk('mockups.sport', ['slug' => $sport['slug']]) }}">{{ $sport['name'] }}</a></td>
                        <td>{{ $sport['family_label'] }}</td>
                        <td>{{ $sport['body'] !== '' ? 'Written' : 'Missing' }}</td>
                        <td>{{ $sport['clubs_count'] }}</td>
                        <td>{{ $sport['ranges_count'] }}</td>
                        <td>{{ $sport['upcoming_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <aside class="mk-internal"><span>Reviewer note</span><p>Follower counts are not stored per sport in a way this screen can show. The follows table is currently empty.</p></aside>
</x-mockups.admin-layout>
