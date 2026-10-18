<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits\Schemas;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClubGuestVisitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Guest')
                    ->columns(2)
                    ->schema([
                        Select::make('organisation_id')
                            ->label('Club')
                            ->relationship(
                                name: 'organisation',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $user = Auth::user();

                                    if ($user?->is_staff) {
                                        return $query->clubs()->orderBy('name');
                                    }

                                    return $query->whereIn('id', ClubMemberResource::managedClubIds())
                                        ->orderBy('name');
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->default(fn (): ?int => Auth::user()?->is_staff
                                ? null
                                : (ClubMemberResource::managedClubIds()[0] ?? null)),
                        TextInput::make('name')->required()->maxLength(120),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(40),
                        DatePicker::make('visit_date')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        Select::make('host_member_id')
                            ->label('Hosting member')
                            ->options(function (callable $get): array {
                                $organisationId = (int) ($get('organisation_id') ?? 0);
                                if ($organisationId <= 0) {
                                    return [];
                                }

                                return ClubMember::query()
                                    ->where('organisation_id', $organisationId)
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get(['id', 'first_name', 'last_name'])
                                    ->mapWithKeys(fn (ClubMember $member): array => [
                                        $member->id => $member->fullName(),
                                    ])
                                    ->all();
                            })
                            ->searchable(),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
