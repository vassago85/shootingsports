<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\ManageCalendarImports;
use App\Imports\CalendarImportRunner;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Support\HtmlString;

/**
 * iOS Safari will not treat a password field as a password field unless
 * type="password" is in the HTML, and Password AutoFill does not fire
 * the input event Livewire's default wire:model listens for. Filament's
 * reveal toggle omits the type and binds the value live, so the admin
 * form submits empty credentials on iPhone while desktop Chrome works.
 * wire:model.blur copies the DOM value into Livewire on submit.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null && Filament::getCurrentPanel()?->getId() === 'admin' && auth()->user()?->is_staff) {
            $this->notifyCalendarImportProblems();
        }

        return $response;
    }

    private function notifyCalendarImportProblems(): void
    {
        $problems = app(CalendarImportRunner::class)->problems();

        if ($problems === []) {
            return;
        }

        Notification::make()
            ->title('Calendar imports need attention')
            ->body(new HtmlString(collect($problems)->map(fn (string $problem): string => e($problem))->implode('<br><br>')))
            ->warning()
            ->persistent()
            ->actions([
                Action::make('open')
                    ->label('Open calendar imports')
                    ->url(ManageCalendarImports::getUrl(panel: 'admin')),
            ])
            ->send();
    }

    protected function getEmailFormComponent(): Component
    {
        $field = parent::getEmailFormComponent();

        if (! $field instanceof TextInput) {
            return $field;
        }

        return $field
            ->autocomplete('username')
            ->autocapitalize('none')
            ->stateBindingModifiers(['blur']);
    }

    protected function getPasswordFormComponent(): Component
    {
        $field = parent::getPasswordFormComponent();

        if (! $field instanceof TextInput) {
            return $field;
        }

        return $field
            ->revealable(false)
            ->stateBindingModifiers(['blur']);
    }
}
