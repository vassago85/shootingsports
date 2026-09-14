<?php

namespace App\Filament\Desk\Resources\Organisations\Schemas;

use App\Enums\OrganisationType;
use App\Enums\Province;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
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
