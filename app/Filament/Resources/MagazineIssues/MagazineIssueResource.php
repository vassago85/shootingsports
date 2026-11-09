<?php

namespace App\Filament\Resources\MagazineIssues;

use App\Filament\Resources\MagazineIssues\Pages\CreateMagazineIssue;
use App\Filament\Resources\MagazineIssues\Pages\EditMagazineIssue;
use App\Filament\Resources\MagazineIssues\Pages\ListMagazineIssues;
use App\Filament\Resources\MagazineIssues\Pages\ManageMagazineHotspots;
use App\Models\MagazineIssue;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MagazineIssueResource extends Resource
{
    protected static ?string $model = MagazineIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?string $navigationLabel = 'Magazine issues';

    protected static ?string $modelLabel = 'magazine issue';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('publication')
                ->label('Magazine')
                ->default('On Target Africa')
                ->required()
                ->maxLength(160),
            TextInput::make('title')
                ->required()
                ->maxLength(160),
            TextInput::make('issue_number')
                ->label('Issue number')
                ->numeric()
                ->minValue(1),
            DatePicker::make('issue_month')
                ->label('Month')
                ->native(false)
                ->displayFormat('F Y')
                ->required(),
            FileUpload::make('pdf_path')
                ->label('PDF')
                ->disk('media')
                ->directory('magazines/pdf')
                ->visibility('public')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(60 * 1024)
                ->required()
                ->columnSpanFull(),
            FileUpload::make('cover_path')
                ->label('Cover')
                ->disk('media')
                ->directory('magazine-covers')
                ->visibility('public')
                ->image()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('publication')->searchable(),
            TextColumn::make('title')->searchable(),
            TextColumn::make('issue_number')->label('Issue'),
            TextColumn::make('issue_month')->date('F Y'),
            TextColumn::make('link_clicks_count')->counts('linkClicks')->label('Ad clicks'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMagazineIssues::route('/'),
            'create' => CreateMagazineIssue::route('/create'),
            'edit' => EditMagazineIssue::route('/{record}/edit'),
            'hotspots' => ManageMagazineHotspots::route('/{record}/hotspots'),
        ];
    }
}
