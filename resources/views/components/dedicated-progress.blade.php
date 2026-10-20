@props(['user'])

@php
    /** @var \App\Models\User $user */
    $hasTracker = $user->hasDedicatedTracker();
    $progress = $hasTracker ? $user->dedicatedProgress() : null;
    $percent = $hasTracker && $progress['required'] > 0
        ? min(100, (int) round(($progress['logged'] / $progress['required']) * 100))
        : 0;
    $daysLeft = $hasTracker ? $progress['days_to_renewal'] : 0;
    $overdue = $hasTracker && $daysLeft < 0;
@endphp

@if ($hasTracker)
    <div class="dedicated-tracker" role="group" aria-label="Dedicated status progress">
        <div class="dedicated-tracker-head">
            <div>
                <p class="label" style="margin:0 0 4px">Dedicated status · {{ $progress['body_label'] }}</p>
                <strong>
                    {{ $progress['logged'] }} of {{ $progress['required'] }} activities this cycle
                </strong>
            </div>
            <div class="dedicated-tracker-meta">
                @if ($overdue)
                    <span class="pill" style="background:#f8d7da;border-color:#f1aeb5;color:#842029">
                        Cycle closed {{ abs($daysLeft) }} {{ \Illuminate\Support\Str::plural('day', abs($daysLeft)) }} ago
                    </span>
                @else
                    <span class="pill" style="background:transparent;border:1px solid var(--rule)">
                        {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} to {{ $progress['window_end']->format('j M') }}
                    </span>
                @endif
                <span class="muted" style="font-size:12px">
                    {{ $progress['window_start']->format('j M Y') }} → {{ $progress['window_end']->format('j M Y') }}
                </span>
            </div>
        </div>

        <div class="dedicated-tracker-bar" aria-hidden="true">
            <span style="width:{{ $percent }}%"></span>
        </div>
        <p class="sr-only">{{ $percent }} percent complete.</p>

        <div class="dedicated-tracker-actions">
            <a class="btn" href="{{ route('my-log.dedicated.print') }}">Print activity record</a>
            <a class="btn ghost" href="{{ route('my-log.export.dedicated') }}">Export CSV</a>
            <a class="btn ghost" href="{{ route('settings.dedicated-status') }}">Edit</a>
        </div>
    </div>
@elseif ($user->isPro())
    <div class="dedicated-tracker dedicated-tracker-empty">
        <div>
            <p class="label" style="margin:0 0 4px">Dedicated status</p>
            <strong>Track your renewal activity automatically.</strong>
            <p class="muted" style="margin:4px 0 0;font-size:13px">
                Pick your body (NRAPA, NATSHOOT or FOSA), fill in your membership and status numbers,
                and we count the matches you log here.
            </p>
        </div>
        <a class="btn" href="{{ route('settings.dedicated-status') }}">Set up</a>
    </div>
@endif
