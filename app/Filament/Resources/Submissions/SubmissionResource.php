<?php

namespace App\Filament\Resources\Submissions;

use App\Actions\MergeSubmission;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Filament\Resources\Submissions\Pages\EditSubmission;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Submission;
use App\Support\RecordLinker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SubmissionResource extends Resource
{
    protected static ?string $model = Submission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        // Submissions come from the public correction forms. Staff only
        // merge, reject, or close them.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(SubmissionType::class)
                    ->required(),
                Textarea::make('payload')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('submitter_name')
                    ->required(),
                TextInput::make('submitter_email')
                    ->email()
                    ->required(),
                Select::make('status')
                    ->options(SubmissionStatus::class)
                    ->default('pending')
                    ->required(),
                Placeholder::make('merged_into')
                    ->label('Merged into')
                    ->content(fn (?Submission $record): string => RecordLinker::label($record?->mergedInto)),
                Select::make('moderator_id')
                    ->relationship('moderator', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('submitter_name')
                    ->searchable(),
                TextColumn::make('submitter_email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('merged_into')
                    ->label('Merged into')
                    ->getStateUsing(fn (Submission $record): string => $record->merged_into_type
                        ? RecordLinker::label($record->mergedInto)
                        : '—')
                    ->url(fn (Submission $record): ?string => RecordLinker::adminUrl($record->mergedInto))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('moderator.name')
                    ->label('Moderator')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(SubmissionStatus::class)->default('pending'),
                SelectFilter::make('type')->options(SubmissionType::class),
            ])
            ->recordActions([
                Action::make('merge')
                    ->visible(fn (Submission $record): bool => $record->status === SubmissionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (Submission $record): void {
                        app(MergeSubmission::class)($record, auth()->user());
                        Notification::make()->title('Submission merged.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubmissions::route('/'),
            'edit' => EditSubmission::route('/{record}/edit'),
        ];
    }
}
