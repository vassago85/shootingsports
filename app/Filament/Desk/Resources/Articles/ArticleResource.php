<?php

namespace App\Filament\Desk\Resources\Articles;

use App\Enums\EventKind;
use App\Filament\Desk\Resources\Articles\Pages\CreateArticle;
use App\Filament\Desk\Resources\Articles\Pages\EditArticle;
use App\Filament\Desk\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Discipline;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Stories';

    protected static ?string $navigationLabel = 'Articles';

    protected static ?string $modelLabel = 'article';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->is_staff || $user?->is_media_partner);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('excerpt')->maxLength(280)->columnSpanFull(),
            Textarea::make('body')->required()->rows(12)->columnSpanFull(),
            CheckboxList::make('disciplines')
                ->relationship('disciplines', 'name')
                ->options(fn (): array => Discipline::query()->where('is_published', true)->orderBy('name')->pluck('name', 'id')->all())
                ->columns(2)
                ->columnSpanFull(),
            CheckboxList::make('event_kinds')
                ->label('Event types')
                ->options(collect(EventKind::cases())->mapWithKeys(fn (EventKind $kind): array => [$kind->value => $kind->getLabel()])->all())
                ->columns(2)
                ->columnSpanFull(),
            DateTimePicker::make('published_at')
                ->label('Publish at')
                ->seconds(false)
                ->helperText('Leave empty to keep this as a draft.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('published_at')->dateTime('j M Y H:i')->placeholder('Draft'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->is_staff) {
            return $query;
        }

        return $query->where('user_id', $user?->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
