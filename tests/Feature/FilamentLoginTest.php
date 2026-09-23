<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the admin login so iOS password autofill can fill it', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('type="password"', false)
        ->assertSee('autocomplete="username"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('wire:model.blur="data.email"', false)
        ->assertSee('wire:model.blur="data.password"', false)
        ->assertDontSee('x-bind:type', false);
});

it('renders the desk login so iOS password autofill can fill it', function () {
    $this->get('/desk/login')
        ->assertOk()
        ->assertSee('type="password"', false)
        ->assertSee('wire:model.blur="data.password"', false)
        ->assertDontSee('x-bind:type', false);
});

it('signs staff in through the admin login form', function () {
    $staff = User::factory()->staff()->create([
        'email' => 'sara@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'sara@example.test',
            'password' => 'longenoughpassword',
        ])
        ->call('authenticate')
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($staff);
});

it('rejects a non-staff user on the admin login form', function () {
    User::factory()->create([
        'email' => 'sam@example.test',
        'password' => Hash::make('longenoughpassword'),
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'sam@example.test',
            'password' => 'longenoughpassword',
        ])
        ->call('authenticate')
        ->assertHasErrors(['data.email']);

    $this->assertGuest();
});
