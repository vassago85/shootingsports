<?php

namespace App\Filament\Desk\Resources\ClubMembers\Schemas;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\Organisation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClubMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Member')
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
                            ->default(fn (): ?int => Auth::user()?->is_staff
                                ? null
                                : (ClubMemberResource::managedClubIds()[0] ?? null)),
                        TextInput::make('member_number')
                            ->maxLength(60)
                            ->helperText('Optional — leave blank to assign later.'),
                        TextInput::make('first_name')->required()->maxLength(120),
                        TextInput::make('last_name')->required()->maxLength(120),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(40),
                        DatePicker::make('date_of_birth')
                            ->native(false)
                            ->maxDate(now()),
                        Select::make('category')
                            ->options(ClubMemberCategory::class)
                            ->default(ClubMemberCategory::Senior->value)
                            ->required(),
                    ]),

                Section::make('Membership status')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(ClubMemberStatus::class)
                            ->default(ClubMemberStatus::Active->value)
                            ->required()
                            ->helperText('Lapsed is derived automatically when expiry passes.'),
                        DatePicker::make('joined_at')->native(false),
                        DatePicker::make('expires_at')->native(false),
                    ]),

                Section::make('Emergency contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('emergency_contact_name')->maxLength(120),
                        TextInput::make('emergency_contact_phone')->tel()->maxLength(40),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Returns the live `Organisation` for a filled form, used by the
     * resource to decide which actions the current user may run.
     */
    public static function pickOrganisation(?int $id): ?Organisation
    {
        return $id === null ? null : Organisation::query()->find($id);
    }
}
