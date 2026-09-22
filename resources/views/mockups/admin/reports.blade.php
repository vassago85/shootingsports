<x-mockups.admin-layout title="Reports" active="reports">
    <header class="mk-pagehead">
        <h1>Reports</h1>
        <p class="mk-lede">Counts from matches in the last six months. Page views and registration clicks are not stored, so they are not charted.</p>
    </header>
    @php $max = max(1, collect($reports['by_month'])->max('count') ?? 1); @endphp
    <section class="mk-section">
        <h2>Matches by month</h2>
        <div class="mk-bars">
            @forelse ($reports['by_month'] as $row)
                <div class="mk-bar"><span>{{ $row['label'] }}</span><i><b style="width: {{ round($row['count'] / $max * 100) }}%"></b></i><span>{{ $row['count'] }}</span></div>
            @empty
                <p>No matches in the last six months.</p>
            @endforelse
        </div>
    </section>
    <section class="mk-section">
        <h2>Matches by sport</h2>
        <div class="mk-bars">
            @foreach ($reports['by_sport'] as $row)
                @php $sportMax = max(1, collect($reports['by_sport'])->max('count')); @endphp
                <div class="mk-bar"><span>{{ $row['label'] }}</span><i><b style="width: {{ round($row['count'] / $sportMax * 100) }}%"></b></i><span>{{ $row['count'] }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="mk-section">
        <h2>Matches by province</h2>
        <div class="mk-bars">
            @foreach ($reports['by_province'] as $row)
                @php $provinceMax = max(1, collect($reports['by_province'])->max('count')); @endphp
                <div class="mk-bar"><span>{{ $row['label'] }}</span><i><b style="width: {{ round($row['count'] / $provinceMax * 100) }}%"></b></i><span>{{ $row['count'] }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="mk-section">
        <h2>Register</h2>
        <dl class="mk-dl">
            <dt>Active clubs</dt><dd>{{ $reports['clubs'] }}</dd>
            <dt>Active ranges</dt><dd>{{ $reports['ranges'] }}</dd>
            <dt>User accounts</dt><dd>{{ $reports['users'] }}</dd>
            <dt>Follows</dt><dd>{{ $reports['follows'] }}</dd>
            <dt>Ad impressions</dt><dd>{{ $reports['impressions'] }}</dd>
            <dt>Ad clicks</dt><dd>{{ $reports['clicks'] }}</dd>
        </dl>
    </section>
</x-mockups.admin-layout>
