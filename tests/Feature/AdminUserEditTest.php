<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('saves a user without replacing the password', function () {
    $staff = User::factory()->staff()->create();
    $user = User::factory()->create();
    $existingPassword = $user->password;

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'Jannie Jacobs',
            'password' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Jannie Jacobs')
        ->and($user->password)->toBe($existingPassword)
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('replaces the password when a new one is entered', function () {
    $staff = User::factory()->staff()->create();
    $user = User::factory()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'password' => 'range-day-42',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('range-day-42', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse();
});

it('rejects a new user with no password', function () {
    $staff = User::factory()->staff()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($staff)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'New Shooter',
            'email' => 'new-shooter@example.test',
            'password' => '',
            'digest_frequency' => 'none',
            'is_match_director' => false,
            'is_staff' => false,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);

    expect(User::query()->where('email', 'new-shooter@example.test')->exists())->toBeFalse();
});
