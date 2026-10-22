<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Schemas;

use App\Models\MagazineIssue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MagazineIssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Issue')
                    ->description('New issues are saved as drafts. Set a publish time to go live.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('publication')
                            ->label('Magazine')
                            ->required()
                            ->default('Targets')
                            ->helperText('The magazine name, such as On Target.'),
                        Placeholder::make('owner_name')
                            ->label('Owner')
                            ->content(function (?MagazineIssue $record): string {
                                $name = $record?->author?->name ?? auth()->user()?->name;

                                return is_string($name) && $name !== ''
                                    ? $name
                                    : 'The account that saves this issue';
                            })
                            ->helperText('Shown on the public list. Another publisher hosts theirs under their own account.'),
                        Select::make('language')
                            ->required()
                            ->default('en')
                            ->options([
                                'en' => 'English',
                                'af' => 'Afrikaans',
                                'zu' => 'isiZulu',
                                'xh' => 'isiXhosa',
                                'st' => 'Sesotho',
                                'tn' => 'Setswana',
                            ]),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(160)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, mixed $livewire): void {
                                if ($livewire instanceof CreateRecord && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->columnSpanFull()
                            ->helperText('Auto-generated from the title. Edit only if you need a custom URL.')
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        TextInput::make('issue_label')
                            ->maxLength(80)
                            ->helperText('Human label such as "October 2026" or "Vol. 12, Issue 4".'),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->seconds(false)
                            ->helperText('Leave empty to keep this as a draft.'),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Short summary used in link previews and search results.'),
                    ]),

                Section::make('Files')
                    ->description('Stored on the media volume. Keep PDFs under 50 MB — the server upload limits match this cap.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('pdf_path')
                            ->label('Magazine PDF')
                            ->required()
                            ->disk('media')
                            ->directory('magazines/pdf')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(50 * 1024)
                            ->columnSpanFull(),
                        Toggle::make('pdf_download_enabled')
                            ->label('Allow PDF download')
                            ->inline(false)
                            ->default(true)
                            ->columnSpanFull()
                            ->helperText('Shows a Download PDF button on the reader. Turn off to keep the issue in the flipbook only.'),
                        FileUpload::make('cover_path')
                            ->label('Cover image')
                            ->disk('media')
                            ->directory('magazines/covers')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth(1200)
                            ->maxSize(4096)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull()
                            ->helperText('Portrait or square cover for listings and social previews. Leave empty to auto-extract the PDF\'s first page.'),
                    ]),
            ]);
    }
}
