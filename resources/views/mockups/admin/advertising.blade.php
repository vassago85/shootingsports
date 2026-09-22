<x-mockups.admin-layout title="Advertising" active="advertising">
    <header class="mk-pagehead">
        <h1>Advertising</h1>
        <p class="mk-lede">Placements already in the register. Sponsored units stay labelled on public pages and do not change organic match, club or range order.</p>
    </header>
    <div class="mk-table-wrap">
        <table class="mk-table">
            <thead>
                <tr>
                    <th>Advertiser</th>
                    <th>Campaign</th>
                    <th>Placement</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($placements as $placement)
                    <tr>
                        <td>{{ $placement['advertiser'] }}</td>
                        <td>{{ $placement['headline'] }}</td>
                        <td>{{ $placement['placement'] }}</td>
                        <td>{{ $placement['starts'] ?: '—' }}</td>
                        <td>{{ $placement['ends'] ?: '—' }}</td>
                        <td>{{ $placement['impressions'] }}</td>
                        <td>{{ $placement['clicks'] }}</td>
                        <td>{{ $placement['ctr'] }}</td>
                        <td>{{ $placement['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No placements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-mockups.admin-layout>
