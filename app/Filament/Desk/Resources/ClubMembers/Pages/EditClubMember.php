<?php

namespace App\Filament\Desk\Resources\ClubMembers\Pages;

use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubMember;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClubMember extends EditRecord
{
    protected static string $resource = ClubMemberResource::class;

    private ?ClubMemberStatus $statusBeforeSave = null;

    protected function beforeSave(): void
    {
        $member = $this->record;

        if ($member instanceof ClubMember) {
            // Capture the stored status before Filament fills the form
            // values back into the model, so afterSave() can tell if a
            // status edit happened and record it in history.
            $this->statusBeforeSave = $member->getOriginal('status') instanceof ClubMemberStatus
                ? $member->getOriginal('status')
                : ClubMemberStatus::tryFrom((string) $member->getOriginal('status'));
        }
    }

    protected function afterSave(): void
    {
        $member = $this->record;

        if (! $member instanceof ClubMember) {
            return;
        }

        if ($this->statusBeforeSave !== null && $this->statusBeforeSave !== $member->status) {
            $member->statusChanges()->create([
                'from_status' => $this->statusBeforeSave->value,
                'to_status' => $member->status->value,
                'changed_by' => Auth::id(),
                'reason' => 'Edited in Desk',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
