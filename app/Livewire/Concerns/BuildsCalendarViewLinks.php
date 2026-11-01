<?php

namespace App\Livewire\Concerns;

use App\Support\CalendarLinks;

trait BuildsCalendarViewLinks
{
    public function listUrl(): string
    {
        return CalendarLinks::listUrl($this);
    }

    public function monthUrl(): string
    {
        return CalendarLinks::monthUrl($this);
    }

    public function mapUrl(): string
    {
        return CalendarLinks::mapUrl($this);
    }
}
