<?php

namespace App\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MarkVerifiedBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('markVerified')
            ->label('Mark verified')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $records->each(function (Model $record): void {
                    if (method_exists($record, 'markVerified')) {
                        $record->markVerified();
                    }
                });

                Notification::make()->title('Marked verified.')->success()->send();
            });
    }
}
