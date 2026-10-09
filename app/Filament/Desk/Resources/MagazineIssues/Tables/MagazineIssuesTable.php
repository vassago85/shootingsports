<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Tables;

use App\Models\MagazineIssue;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class MagazineIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('issue_label')
                    ->label('Issue')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (MagazineIssue $record): string => $record->isPublished() ? 'Published' : 'Draft')
                    ->color(fn (MagazineIssue $record): string => $record->isPublished() ? 'success' : 'gray'),
                TextColumn::make('page_count')
                    ->label('Pages')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('readers')
                    ->label('Readers')
                    ->getStateUsing(fn (MagazineIssue $record): int => $record->readersCount()),
                TextColumn::make('avg_pages')
                    ->label('Avg pages read')
                    ->getStateUsing(fn (MagazineIssue $record): string => $record->averagePagesRead() > 0
                        ? number_format($record->averagePagesRead(), 1)
                        : '—'),
                TextColumn::make('published_at')
                    ->dateTime('j M Y H:i')
                    ->placeholder('Draft')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('previewLink')
                    ->label('Copy preview link')
                    ->icon(Heroicon::OutlinedLink)
                    ->visible(fn (MagazineIssue $record): bool => $record->pdf_path !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Share a 14-day preview link?')
                    ->modalDescription('Anyone with the link can read the issue, even while it is still a draft. The link expires after 14 days and reader tracking is disabled on it.')
                    ->modalSubmitActionLabel('Generate link')
                    ->action(function (MagazineIssue $record): void {
                        $url = URL::temporarySignedRoute(
                            'magazines.show',
                            now()->addDays(14),
                            ['issue' => $record->slug]
                        );

                        Notification::make()
                            ->title('Preview link ready')
                            ->body($url)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ]);
    }
}
