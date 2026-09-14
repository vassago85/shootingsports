<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Submission;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class MergeSubmission
{
    public function __invoke(Submission $submission, User $moderator, ?Model $into = null): Model
    {
        if ($submission->status !== SubmissionStatus::Pending) {
            throw new InvalidArgumentException('Only pending submissions can be merged.');
        }

        $class = match ($submission->type) {
            SubmissionType::Organisation => Organisation::class,
            SubmissionType::Venue => Venue::class,
            SubmissionType::Event => Event::class,
            SubmissionType::Provider => Provider::class,
        };

        $payload = is_array($submission->payload) ? $submission->payload : [];

        $entity = $into instanceof $class
            ? tap($into)->fill($payload)->save()
            : $class::query()->create($payload);

        $submission->forceFill([
            'status' => SubmissionStatus::Merged,
            'merged_into_type' => $entity->getMorphClass(),
            'merged_into_id' => $entity->getKey(),
            'moderator_id' => $moderator->getKey(),
        ])->save();

        return $entity;
    }
}
