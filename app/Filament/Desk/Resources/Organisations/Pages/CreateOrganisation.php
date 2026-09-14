<?php

namespace App\Filament\Desk\Resources\Organisations\Pages;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\VerificationState;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrganisation extends CreateRecord
{
    protected static string $resource = OrganisationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ListingStatus::Pending->value;
        $data['verification_state'] = VerificationState::Unconfirmed->value;
        $data['source'] = ListingSource::Submission->value;
        $data['accredited'] = false;
        $data['claimed_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->users()->attach(Auth::id(), [
            'role' => OrganisationUserRole::MatchDirector->value,
            'granted_at' => now(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
