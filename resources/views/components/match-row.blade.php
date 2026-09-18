@props(['event'])

@php
    use App\Enums\EventLevel;
    use App\Enums\EventStatus;
    use App\Support\EventDate;

    $matchUrl = route('matches.show', $event->slug);
    $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
    $family = $discipline?->family->value;
    $status = $event->status;
    $level = $event->level;
    $planned = $event->isProvisional();

    $host = $event->hostOrganisation;
    $logoUrl = $host?->logoUrl() ?? $host?->parent?->logoUrl();
    $hostName = $event->hostDisplayName();

    $venue = $event->venue;
    $venueName = $venue?->name;
    $venuePlace = collect([
        $venue?->town,
        $venue?->province?->getLabel() ?? $venue?->province?->code(),
    ])->filter()->implode(' · ');

    // State pill = current lifecycle state (fill colour). Classification
    // tags (level, discipline, flags) render separately as outlined chips.
    $stateLabel = null;
    $stateClass = null;

    if ($status) {
        [$stateLabel, $stateClass] = match ($status) {
            EventStatus::EntriesOpen => ['Entries open', 'is-open'],
            EventStatus::Confirmed => ['Confirmed', 'is-confirmed'],
            EventStatus::Full => ['Full', 'is-full'],
            EventStatus::Planned => ['Provisional', 'is-planned'],
            EventStatus::Cancelled => ['Cancelled', 'is-cancelled'],
            EventStatus::Postponed => ['Postponed', 'is-postponed'],
            EventStatus::Completed => ['Completed', 'is-completed'],
            default => [null, null],
        };
    }
@endphp

{{--
    Whole-row link via title-anchor + ::after overlay pattern (same
    accessibility approach as the DOPE card): the title's <a> stretches
    invisibly across the entire article so clicking anywhere navigates to
    the match detail page. No nested anchors, valid HTML.
--}}
<article class="mb-row {{ $planned ? 'is-planned' : '' }}" data-fam="{{ $family }}">
    <div class="mb-date {{ EventDate::isMultiDay($event->starts_at, $event->ends_at) ? 'is-range' : '' }}">
        <span class="dow">{{ EventDate::weekday($event->starts_at, $event->ends_at) }}</span>
        <b>{{ EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
        <span class="mo">{{ EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
    </div>

    <div class="mb-info">
        <div class="mb-info-main">
            @if ($logoUrl)
                <div class="mb-logo">
                    <img
                        src="{{ $logoUrl }}"
                        alt="{{ $hostName }}"
                        title="{{ $hostName }}"
                        loading="lazy"
                        decoding="async"
                        width="40"
                        height="40"
                    >
                </div>
            @endif
            <div class="mb-info-text">
                <h3 class="mb-title-wrap">
                    <a href="{{ $matchUrl }}" class="mb-title-link mb-title">{{ $event->title }}</a>
                </h3>
                <div class="mb-host">{{ $hostName }}</div>
                @if (filled($venueName) || filled($venuePlace))
                    <div class="mb-place">
                        @if (filled($venueName)){{ $venueName }}@endif
                        @if (filled($venueName) && filled($venuePlace)) · @endif
                        @if (filled($venuePlace)){{ $venuePlace }}@endif
                    </div>
                @elseif ($event->locationLabel() !== '')
                    <div class="mb-place">{{ $event->locationLabel() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="mb-meta">
        <div class="mb-tags">
            @if ($discipline)
                <span class="mb-tag">{{ $discipline->name }}</span>
            @endif
            @if ($level === EventLevel::National || $level === EventLevel::International)
                <span class="mb-tag is-national">{{ $level->getLabel() }}</span>
            @elseif ($level === EventLevel::Series)
                <span class="mb-tag is-series">{{ $level->getLabel() }}</span>
            @endif
            @foreach ($event->flags as $flag)
                @if ($flag->slug === 'new-shooter-friendly')
                    <span class="mb-tag is-novice">{{ $flag->name }}</span>
                @endif
            @endforeach
        </div>
        @if ($stateLabel)
            <span class="mb-state {{ $stateClass }}">{{ $stateLabel }}</span>
        @endif
    </div>

    <div class="mb-action-wrap" aria-hidden="true">
        View match<span class="mb-action-arrow">→</span>
    </div>
</article>
