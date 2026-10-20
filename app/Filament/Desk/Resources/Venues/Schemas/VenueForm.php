<?php

namespace App\Filament\Desk\Resources\Venues\Schemas;

use App\Enums\Province;
use App\Enums\VenueAccess;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Listing')
                    ->description('What the public sees on your range page.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('The public URL for your range. Changes here are coordinated with staff.'),
                        Select::make('province')
                            ->options(Province::class)
                            ->required(),
                        TextInput::make('town'),
                        Select::make('access')
                            ->options(VenueAccess::class)
                            ->default(VenueAccess::MembersOnly)
                            ->required()
                            ->helperText('Public = anyone can shoot here. Members only = ticket of membership required. Guest = book ahead.'),
                        TextInput::make('website_url')
                            ->url()
                            ->prefixIcon('heroicon-o-link')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),

                Section::make('Range capability')
                    ->description('Help shooters work out if the range suits what they shoot.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('bay_count')
                            ->label('Bays / lanes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_distance_m')
                            ->label('Max distance (m)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Longest shootable distance.'),
                        TextInput::make('day_fee_cents')
                            ->label('Day fee (cents)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Zero means free or members-only.'),
                        Textarea::make('notes')
                            ->label('Range officer notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Only shown to staff — safety notes, access protocol, RO notes.'),
                    ]),

                Section::make('Location')
                    ->description('Coordinates are set by staff after verification. If anything looks wrong, update the address and let us know.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address')
                            ->columnSpanFull(),
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('lng')
                            ->label('Longitude')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Logo')
                    ->description('Square PNG or JPEG works best. Stored on the media volume.')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('media')
                            ->directory('venue-logos')
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
