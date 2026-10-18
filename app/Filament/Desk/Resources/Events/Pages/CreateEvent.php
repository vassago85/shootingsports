<?php

namespace App\Filament\Desk\Resources\Events\Pages;

use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventFlags;
use App\Filament\Concerns\SyncsEventVenues;
use App\Filament\Desk\Resources\Events\EventResource;
use App\Filament\Desk\Resources\Events\Schemas\EventForm;
use App\Models\Organisation;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateEvent extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;
    use SyncsEventDisciplines;
    use SyncsEventFlags;
    use SyncsEventVenues;

    protected static string $resource = EventResource::class;

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make('Basics')
                ->description('Club, title, sports and type')
                ->schema(EventForm::basicsComponents())
                ->columns(2),
            Step::make('When and where')
                ->description('Dates, all-day and range')
                ->schema(EventForm::whenWhereComponents())
                ->columns(2),
            Step::make('Entry')
                ->description('Fees, registration and capacity')
                ->schema(EventForm::entryComponents())
                ->columns(2),
            Step::make('Extras and publish')
                ->description('Banner, who it is for, then submit')
                ->schema(EventForm::extrasComponents(forCreate: true)),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractDisciplineIds($data);
        $data = $this->extractAdditionalVenues($data);
        $data = $this->extractFlagIds($data);
        $data['created_by'] ??= Auth::id();
        $data['source'] = ListingSource::Submission->value;
        $data['all_day'] ??= true;
        $data['status'] = EventStatus::Planned->value;

        if (blank($data['slug'] ?? null) && filled($data['title'] ?? null)) {
            $data['slug'] = Str::slug((string) $data['title']);
        }

        $host = Organisation::query()->find($data['host_organisation_id'] ?? null);
        $data['publication'] = ($host?->trusted === true)
            ? EventPublication::Published->value
            : EventPublication::PendingReview->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistDisciplines();
        $this->persistVenues();
        $this->persistFlags();
    }

    protected function hasSkippableSteps(): bool
    {
        // Let directors jump between steps while drafting; each step
        // still validates before submit on the last step.
        return true;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $publication = $this->record->publication;

        return match ($publication) {
            EventPublication::Published => 'Event published',
            EventPublication::PendingReview => 'Event submitted for review',
            default => 'Event saved',
        };
    }
}
