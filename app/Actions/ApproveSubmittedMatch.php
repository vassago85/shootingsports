<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Models\Event;

/**
 * Publish a director-submitted match. Draft stays off the public
 * calendar; confirming it is the staff approval. A host club that
 * was created with the submission is published in the same step,
 * because the calendar only lists matches whose host is published.
 */
class ApproveSubmittedMatch
{
    public function __invoke(Event $event): Event
    {
        if ($event->status !== EventStatus::Draft) {
            return $event;
        }

        $event->confirm();

        $host = $event->hostOrganisation;

        if ($host !== null && $host->status === ListingStatus::Pending && $host->source === ListingSource::Submission) {
            $host->forceFill([
                'status' => ListingStatus::Published,
            ])->save();
        }

        return $event->fresh(['hostOrganisation']) ?? $event;
    }
}
