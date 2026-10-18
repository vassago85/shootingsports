<x-filament-panels::page>
    <div class="ss-today">
        <div class="ss-hdr">
            <div>
                <div class="ss-eyebrow">{{ $today }}</div>
                <h1 class="ss-h1">Today</h1>
            </div>
            <a class="ss-btn" href="{{ $addEventUrl }}">Add event</a>
        </div>

        <div class="ss-grid4">
            @foreach ($tiles as $tile)
                <a class="ss-card ss-tile" href="{{ $tile['href'] }}">
                    <div class="ss-n">{{ $tile['value'] }}</div>
                    <div class="ss-l">{{ $tile['label'] }}</div>
                    @if (! empty($tile['go']))
                        <div class="ss-go">{{ $tile['go'] }}</div>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="ss-grid2 ss-mt">
            <div class="ss-card">
                <h3>Fix these</h3>
                @forelse ($attention as $item)
                    <a class="ss-row ss-row-link" href="{{ $item['href'] }}">
                        <span>{{ $item['title'] }}</span>
                        <span class="ss-row-end">
                            <span class="ss-muted">{{ $item['count'] }}</span>
                            <span class="ss-sev ss-sev-{{ $item['severity'] }}">{{ $item['severity_label'] }}</span>
                        </span>
                    </a>
                @empty
                    <p class="ss-muted" style="margin:0">Nothing flagged right now. Nice work.</p>
                @endforelse
            </div>

            <div class="ss-card">
                <h3>Next 7 days</h3>
                @if (empty($upcoming))
                    <p class="ss-muted" style="margin:0">Nothing on the public calendar in the next week.</p>
                @else
                    <div class="ss-table-wrap">
                        <table class="ss-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event</th>
                                    <th>Club</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcoming as $event)
                                    <tr>
                                        <td>{{ $event['when'] }}</td>
                                        <td><a class="ss-link" href="{{ $event['href'] }}">{{ $event['title'] }}</a></td>
                                        <td class="ss-muted">{{ $event['host'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="3" class="ss-muted">All-day events show the date only, no 02:00.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="ss-card ss-mt">
            <h3>Recent desk activity</h3>
            @forelse ($recent as $row)
                <a class="ss-row ss-row-link" href="{{ $row['href'] }}">
                    <span>{{ $row['text'] }}</span>
                    <span class="ss-sev ss-sev-{{ $row['badge_color'] }}">{{ $row['badge'] }}</span>
                </a>
            @empty
                <p class="ss-muted" style="margin:0">No recent edits.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
