<?php

namespace App\Imports;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\Province;
use Illuminate\Support\Carbon;

class ImportedMatch
{
    public function __construct(
        public string $externalId,
        public string $title,
        public string $entryUrl,
        public Carbon $startsAt,
        public ?Carbon $endsAt,
        public EventLevel $level,
        public EventStatus $status,
        public ?Province $province,
        public ?string $venueName,
        public ?string $disciplineSlug,
        public ?string $description = null,
    ) {}
}
