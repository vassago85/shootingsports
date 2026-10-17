<x-filament-panels::page>
    <div class="desk-hero">
        <p class="label">{{ $today }}</p>
        <h1>Today</h1>
        <p>Welcome back, {{ $name }}.</p>
    </div>

    {{-- Row 1 — four headline tiles. Each number is a link that
         opens the exact filtered list that produced its count. --}}
    <div class="admin-stats admin-stats-headline">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['href'] }}" class="admin-stat admin-stat-{{ $tile['tone'] }}">
                <b>{{ $tile['value'] }}</b>
                <span>{{ $tile['label'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Row 2 — Fix these. Attention rules threaded through to the
         filtered list that produced each count. --}}
    <section class="desk-widget">
        <p class="kicker">Fix these</p>
        @forelse ($attention as $item)
            <a class="admin-row" href="{{ $item['href'] }}">
                <span class="admin-row-title">{{ $item['title'] }}</span>
                <span class="admin-row-meta">{{ $item['problem'] }}</span>
                <span class="admin-badge {{ $item['severity'] }}">{{ $item['severity'] === 'attention' ? 'Needs attention' : ucfirst($item['severity']) }}</span>
            </a>
        @empty
            <p class="admin-quiet">Nothing flagged right now. Nice work.</p>
        @endforelse
    </section>

    {{-- Row 3 — Next 7 days + Recent desk activity, side by side on
         desktop, stacked on mobile. --}}
    <div class="admin-split">
        <section class="desk-widget">
            <p class="kicker">Next 7 days</p>
            @forelse ($upcoming as $event)
                <a class="admin-row" href="{{ $event['href'] }}">
                    <span class="admin-row-title">{{ $event['title'] }}</span>
                    <span class="admin-row-meta">
                        {{ $event['when'] }}
                        @if ($event['host'])
                            · {{ $event['host'] }}
                        @endif
                    </span>
                </a>
            @empty
                <p class="admin-quiet">Nothing on the public calendar in the next week.</p>
            @endforelse
        </section>

        <section class="desk-widget">
            <p class="kicker">Recent activity</p>
            @forelse ($recent as $row)
                <a class="admin-row" href="{{ $row['href'] }}">
                    <span class="admin-row-title">{{ $row['text'] }}</span>
                    <span class="admin-row-meta">{{ $row['when'] }}</span>
                </a>
            @empty
                <p class="admin-quiet">No recent edits.</p>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
