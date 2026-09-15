<x-layouts.public title="My log" description="Your personal attendance record — every match you've shot, exportable for dedicated-status renewals.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My log · {{ $user->name }}</p>
                <h1>Attendance record</h1>
                <p>
                    Every match you have logged, reverse-chronological. Export the year as a PDF or CSV for your accrediting body when it is time to renew dedicated status.
                </p>
                @if ($user->isPro())
                    <p class="label" style="margin-top:8px;color:var(--brass)">Pro · unlimited entries</p>
                @else
                    <p style="margin-top:8px;color:var(--slate)">
                        Free tier: {{ $currentCount }}/{{ $currentCount + ($remainingSlots ?? 0) }} slots used.
                        @if ($remainingSlots === 0)
                            <a href="{{ route('upgrade') }}" style="color:var(--brass)">Go Pro for unlimited history →</a>
                        @endif
                    </p>
                @endif
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:1000px">

                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($totals->isNotEmpty())
                    <div class="my-log-summary">
                        <div>
                            <span>Total {{ $filters['year'] }}</span>
                            <b>{{ $entries->count() }}</b>
                        </div>
                        @foreach ($totals->take(4) as $disciplineName => $count)
                            <div>
                                <span>{{ $disciplineName }}</span>
                                <b>{{ $count }}</b>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="get" action="{{ route('my-log') }}" class="my-log-filters">
                    <label class="field">
                        <span>Year</span>
                        <select name="year" onchange="this.form.submit()">
                            @foreach ($availableYears as $y)
                                <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span>Discipline</span>
                        <select name="discipline" onchange="this.form.submit()">
                            <option value="">All disciplines</option>
                            @foreach ($availableDisciplines as $d)
                                <option value="{{ $d->id }}" @selected($filters['discipline'] === $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div style="margin-left:auto;display:flex;gap:10px;flex-wrap:wrap">
                        <livewire:add-manual-attendance />

                        @can('export-attendance-log')
                            <a class="btn ghost" href="{{ route('my-log.print', ['year' => $filters['year']]) }}" target="_blank" rel="noopener">Print/save as PDF · {{ $filters['year'] }}</a>
                            <a class="btn ghost" href="{{ route('my-log.export.csv', ['year' => $filters['year']]) }}">Export CSV</a>
                        @else
                            <button
                                type="button"
                                class="btn"
                                onclick="Livewire.dispatch('open-upgrade-prompt', { trigger: 'export_attendance_log' })"
                            >Export for renewals</button>
                        @endcan
                    </div>
                </form>

                @if ($entries->isEmpty())
                    <div class="empty">
                        <p><b>No matches logged for {{ $filters['year'] }}{{ $filters['discipline'] ? ' in this discipline' : '' }}.</b></p>
                        <p style="margin-top:8px">Click <b>I shot this</b> on any match page to add it — or use <b>+ Add a match</b> above for events that are not in our calendar.</p>
                    </div>
                @else
                    <table class="my-log-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Match</th>
                                <th>Discipline</th>
                                <th>Host</th>
                                <th>Venue</th>
                                <th>Div / Class</th>
                                <th class="num">Placing</th>
                                <th>Score</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td>{{ $entry->event_date?->format('j M') }}</td>
                                    <td>
                                        @if ($entry->event_id && $entry->event)
                                            <a href="{{ route('matches.show', $entry->event->slug) }}">{{ $entry->event_name_snapshot }}</a>
                                        @else
                                            {{ $entry->event_name_snapshot }}
                                        @endif
                                    </td>
                                    <td>{{ $entry->discipline_name_snapshot ?? '—' }}</td>
                                    <td>{{ $entry->host_snapshot ?? '—' }}</td>
                                    <td>{{ $entry->venue_snapshot ?? '—' }}</td>
                                    <td>
                                        @if ($entry->division || $entry->classification)
                                            {{ $entry->division }}@if ($entry->division && $entry->classification) · @endif{{ $entry->classification }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($entry->placing)
                                            {{ $entry->placing }}@if ($entry->field_size) / {{ $entry->field_size }} @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $entry->score ?? '—' }}</td>
                                    <td class="row-actions">
                                        <form method="post" action="{{ route('my-log.destroy', $entry->id) }}" onsubmit="return confirm('Remove this match from your log?');" style="display:inline">
                                            @csrf
                                            @method('delete')
                                            <button type="submit">delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
