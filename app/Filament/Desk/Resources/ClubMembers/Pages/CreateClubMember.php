<?php

namespace App\Filament\Desk\Resources\ClubMembers\Pages;

use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubMember;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClubMember extends CreateRecord
{
    protected static string $resource = ClubMemberResource::class;

    protected function afterCreate(): void
    {
        $member = $this->record;

        if (! $member instanceof ClubMember) {
            return;
        }

        $member->statusChanges()->create([
            'from_status' => null,
            'to_status' => ($member->status ?? ClubMemberStatus::Active)->value,
            'changed_by' => Auth::id(),
            'reason' => 'Created in Desk',
        ]);
    }
}
