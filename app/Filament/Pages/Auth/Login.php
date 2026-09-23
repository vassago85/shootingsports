<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

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
