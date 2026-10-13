<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\TeamEntry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Team entries submitted on the match page. Rows are read-only: the
 * organiser already has the emailed copy and confirms entries offline.
 */
class TeamEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'teamEntries';

    protected static ?string $title = 'Team entries';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Event
            && ($ownerRecord->accepts_team_entries || $ownerRecord->teamEntries()->exists());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('team_name')->label('Team')->searchable(),
                TextColumn::make('captain_name')->label('Captain')->searchable(),
                TextColumn::make('captain_email')->label('Email')->copyable(),
                TextColumn::make('captain_phone')->label('Phone'),
                TextColumn::make('proof_of_payment_path')
                    ->label('Proof of payment')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Uploaded' : 'Not uploaded')
                    ->placeholder('Not uploaded')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime('j M Y H:i')->label('Submitted'),
            ])
            ->recordActions([
                Action::make('shooters')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->color('gray')
                    ->modalHeading(fn (TeamEntry $record): string => $record->team_name)
                    ->modalContent(fn (TeamEntry $record) => view('filament.team-entry-shooters', ['entry' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('proofOfPayment')
                    ->label('Proof of payment')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (TeamEntry $record): bool => filled($record->proof_of_payment_path))
                    ->action(fn (TeamEntry $record): StreamedResponse => Storage::disk(TeamEntry::PROOF_OF_PAYMENT_DISK)
                        ->download($record->proof_of_payment_path, $record->proof_of_payment_name)),
                DeleteAction::make()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_staff),
            ]);
    }
}
