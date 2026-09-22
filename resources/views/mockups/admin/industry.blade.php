<x-mockups.admin-layout title="Industry" active="industry">
    <header class="mk-pagehead">
        <h1>Industry</h1>
    </header>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr><th>Business</th><th>Category</th><th>Province</th><th>Verified</th><th>Featured</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($businesses as $business)
                    <tr>
                        <td><a href="{{ $mk('mockups.business', ['slug' => $business['slug']]) }}">{{ $business['name'] }}</a></td>
                        <td>{{ $business['category'] ?: 'Missing' }}</td>
                        <td>{{ $business['province'] ?: '—' }}</td>
                        <td>{{ $business['verified'] ? 'Verified' : 'Unverified' }}</td>
                        <td>{{ $business['featured'] ? 'Featured' : '—' }}</td>
                        <td>{{ $business['tier_label'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No businesses published.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <aside class="mk-internal"><span>Reviewer note</span><p>Profile views are not stored on businesses. Placement impressions live under Advertising.</p></aside>
</x-mockups.admin-layout>
