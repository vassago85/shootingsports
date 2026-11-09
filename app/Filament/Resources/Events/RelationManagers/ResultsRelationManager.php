<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\EntryStatus;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\EventEntry;
use App\Models\EventResult;
use App\Services\ImpactScoring\ImpactResultsImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

class ResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'results';

    protected static ?string $title = 'Results';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Shooter')->searchable(),
                TextColumn::make('discipline.name')->label('Sport'),
                TextColumn::make('division')->placeholder('—'),
                TextColumn::make('placing'),
                TextColumn::make('field_size')->label('Field'),
                TextColumn::make('score')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('importImpact')
                    ->label('Import from Impact')
                    ->modalHeading('Import from Impact')
                    ->modalDescription('Paste the Impact match page or results link. The same URL SAPRF uses.')
                    ->modalSubmitActionLabel('Import')
                    ->schema(function (): array {
                        $event = $this->getOwnerRecord();
                        $fields = [
                            TextInput::make('impact_ref')
                                ->label('Impact match URL')
                                ->placeholder('https://www.impactscoring.net/match/view?id=10558')
                                ->helperText('The match page, the results link, or the match number.')
                                ->required()
                                ->maxLength(500),
                            Checkbox::make('replace_existing')
                                ->label('Replace results already on this match')
                                ->default(true),
                        ];

                        if ($event instanceof Event && $event->disciplines()->count() > 1) {
                            $fields[] = Select::make('discipline_id')
                                ->label('Sport')
                                ->options(fn (): array => $event->disciplines()->orderBy('name')->pluck('name', 'disciplines.id')->all())
                                ->required();
                        }

                        return $fields;
                    })
                    ->action(function (array $data): void {
                        $event = $this->getOwnerRecord();

                        if (! $event instanceof Event) {
                            return;
                        }

                        try {
                            $imported = app(ImpactResultsImporter::class)->import(
                                $event,
                                (string) $data['impact_ref'],
                                (bool) ($data['replace_existing'] ?? true),
                                isset($data['discipline_id']) ? (int) $data['discipline_id'] : null,
                            );
                        } catch (RuntimeException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Imported '.$imported['count'].' results from '.$imported['name'].'.')
                            ->success()
                            ->send();
                    }),
                Action::make('fromEntries')
                    ->label('Add entered shooters')
                    ->action(function (): void {
                        $event = $this->getOwnerRecord();

                        if (! $event instanceof Event) {
                            return;
                        }

                        $disciplineId = $event->disciplines()->orderBy('disciplines.id')->value('disciplines.id');

                        $event->entries()
                            ->where('status', EntryStatus::Entered)
                            ->with('user')
                            ->get()
                            ->each(function (EventEntry $entry) use ($event, $disciplineId): void {
                                EventResult::query()->firstOrCreate(
                                    [
                                        'event_id' => $event->id,
                                        'user_id' => $entry->user_id,
                                    ],
                                    [
                                        'display_name' => $entry->user?->name ?? 'Shooter',
                                        'discipline_id' => $disciplineId,
                                    ],
                                );
                            });
                    }),
                CreateAction::make()
                    ->label('Add a result')
                    ->form(self::fields()),
            ])
            ->recordActions([
                EditAction::make()->form(self::fields()),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return list<Select|TextInput>
     */
    private static function fields(): array
    {
        return [
            TextInput::make('display_name')->label('Name')->required()->maxLength(160),
            Select::make('user_id')
                ->label('Linked account')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Rankings and the public profile only count results linked to an account.'),
            Select::make('discipline_id')
                ->label('Sport')
                ->options(fn (): array => Discipline::query()->where('is_published', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            TextInput::make('division')->maxLength(80),
            TextInput::make('placing')->numeric()->minValue(1),
            TextInput::make('field_size')->numeric()->minValue(1),
            TextInput::make('score')->maxLength(40),
        ];
    }
}
