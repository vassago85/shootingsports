@props(['event'])

@php
    $status = $event->status;
    $pills = [];

    if ($status?->isConfirmedDate()) {
        $pills[] = ['confirmed', 'Confirmed'];
    }

    if ($status) {
        $label = $status->getLabel();
        $class = $status->pillClass();
        if ($status->isConfirmedDate() && $status !== \App\Enums\EventStatus::Confirmed) {
            $pills[] = [$class, $label];
        } elseif (! $status->isConfirmedDate()) {
            $pills[] = [$class, $label];
        }
    }

    if ($event->level === \App\Enums\EventLevel::Series) {
        $pills[] = ['series', $event->level->getLabel()];
    } elseif (in_array($event->level, [\App\Enums\EventLevel::National, \App\Enums\EventLevel::International], true)) {
        $pills[] = ['nat', $event->level->getLabel()];
    }

    foreach ($event->flags as $flag) {
        if ($flag->slug === 'new-shooter-friendly') {
            $pills[] = ['novice', $flag->name];
        }
    }
@endphp

@foreach ($pills as [$class, $label])
    <span {{ $attributes->class(['pill', $class]) }}>{{ $label }}</span>
@endforeach
