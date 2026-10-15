<?php

namespace App\Filament\Desk\Resources\Organisations\Schemas;

use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Organisation;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listing')
                    ->description('New listings are saved as draft. Staff publish them to the public directory.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                                if ($livewire instanceof CreateRecord && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->helperText('Auto-generated from the name. Edit only if you need a custom URL.')
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        TextInput::make('short_name')
                            ->helperText('Optional short label for tight layouts.'),
                        Select::make('type')
                            ->options([
                                OrganisationType::Club->value => 'Club',
                                OrganisationType::Association->value => 'Association',
                                OrganisationType::Series->value => 'Series (recurring match brand)',
                            ])
                            ->required()
                            ->default(OrganisationType::Club->value)
                            ->helperText('Use Series for branded monthly matches that are not a formal club (e.g. Royal Flush).'),
                        Select::make('province')
                            ->options(Province::class),
                        TextInput::make('town'),
                        Toggle::make('visitors_welcome')
                            ->label('Visitors welcome')
                            ->inline(false)
                            ->default(false),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->email(),
                        TextInput::make('phone')
                            ->tel(),
                        TextInput::make('website_url')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('facebook_url')
                            ->url()
                            ->columnSpanFull(),
                    ]),

                Section::make('Membership register')
                    ->description('Turn on to manage members for this club in Desk. Applications only open when both switches are on.')
                    ->columns(2)
                    ->visible(function (?Organisation $record): bool {
                        $user = Auth::user();

                        if (! $user instanceof User) {
                            return false;
                        }

                        // On create the section is visible to any user
                        // who could manage a members register anywhere.
                        // On edit it is only shown for clubs the user
                        // actually manages, so a match director without
                        // the member-admin role never sees these toggles.
                        return $record === null
                            ? ($user->is_staff || $user->holdsAnyMemberManagingRole())
                            : $user->canManageClubMembers($record);
                    })
                    ->schema([
                        Toggle::make('membership_enabled')
                            ->label('Enable member register')
                            ->inline(false)
                            ->live()
                            ->default(false),
                        Toggle::make('accepts_applications')
                            ->label('Accept online applications')
                            ->helperText('Shows an Apply to join button on the public club page.')
                            ->inline(false)
                            ->disabled(fn (Get $get): bool => ! (bool) $get('membership_enabled'))
                            ->default(false),
                    ]),

                Section::make('Logo')
                    ->description('Stored on the media volume (extra HDD in production). Square PNG or JPEG works best.')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('media')
                            ->directory('organisation-logos')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth(512)
                            ->imageResizeTargetHeight(512)
                            ->maxSize(3072)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    ]),
            ]);
    }
}
