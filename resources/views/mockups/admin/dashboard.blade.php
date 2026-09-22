<x-mockups.admin-layout title="Dashboard" active="dashboard">
    <header class="mk-pagehead">
        <h1>ShootingSports Admin</h1>
        <p class="mk-lede">What needs doing on the register.</p>
    </header>
    <div class="strip" style="margin:12px 0 20px">
        <div class="strip-in">
            <div><b>{{ $stats['matches'] }}</b><span>Upcoming matches</span></div>
            <div><b>{{ $stats['clubs'] }}</b><span>Clubs</span></div>
            <div><b>{{ $stats['ranges'] }}</b><span>Ranges</span></div>
            <div><b>{{ $stats['sports'] }}</b><span>Sports</span></div>
            <div><b>{{ $businesses }}</b><span>Industry</span></div>
            <div><b>{{ $users }}</b><span>Users</span></div>
        </div>
    </div>
    <section class="mk-section">
        <h2>Needs attention</h2>
        <div class="mk-meta" style="margin-bottom:12px">
            @foreach ($summary as $item)
                <span class="mk-badge {{ $item['severity'] }}">{{ $item['count'] }} {{ $item['label'] }}</span>
            @endforeach
        </div>
        @forelse ($attention as $item)
            <a class="mk-row" href="{{ $item['href'] }}">
                <span class="mk-row-title">{{ $item['title'] }}</span>
                <span class="mk-row-meta">{{ $item['problem'] }}</span>
                <span class="mk-badge {{ $item['severity'] }}">{{ $item['severity'] === 'attention' ? 'Needs attention' : ucfirst($item['severity']) }}</span>
            </a>
        @empty
            <p>Nothing is flagged.</p>
        @endforelse
        <p style="margin-top:12px"><a class="mk-textlink" href="{{ $mk('mockups.admin.quality') }}">Open the quality centre</a></p>
    </section>
    <section class="mk-section">
        <h2>Upcoming activity</h2>
        <ul class="mk-activity">
            @foreach ($activity as $item)
                <li><time>{{ $item['when'] }}</time><span>{{ $item['text'] }}</span></li>
            @endforeach
        </ul>
        <h3 style="margin:18px 0 8px;font-size:16px;text-transform:uppercase">Newest records</h3>
        @foreach ($recent as $match)
            <p><a href="{{ $mk('mockups.admin.matches.edit', ['slug' => $match['slug']]) }}">{{ $match['date_label'] }} — {{ $match['title'] }}</a></p>
        @endforeach
    </section>
    <section class="mk-section">
        <h2>Recent submissions</h2>
        <p>The submissions queue is empty. Club claims, match edits and range corrections will land on the submissions screen.</p>
        <a class="mk-textlink" href="{{ $mk('mockups.admin.submissions') }}">Open submissions</a>
    </section>
    <section class="mk-section">
        <h2>Platform activity</h2>
        <p>{{ $users }} user accounts. Follows, match views and registration clicks are not all stored yet. Advertising impressions and clicks are on the advertising screen.</p>
    </section>
    <section class="mk-section">
        <h2>Quick actions</h2>
        <div class="mk-actions">
            <a class="btn" href="{{ $mk('mockups.admin.matches.edit', ['new' => 1]) }}">Add match</a>
            <a class="btn ghost" href="{{ $mk('mockups.admin.clubs', ['new' => 1]) }}">Add club</a>
            <a class="btn ghost" href="{{ $mk('mockups.admin.ranges', ['new' => 1]) }}">Add range</a>
            <a class="btn ghost" href="{{ $mk('mockups.admin.industry', ['new' => 1]) }}">Add business</a>
        </div>
    </section>
</x-mockups.admin-layout>
