<?php

use App\Livewire\Auth\Register;
use App\Mail\NewRegistrationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('registration.notify_emails', [
        'dirk@example.test',
        'di@example.test',
    ]);
});

it('emails Dirk and Di when someone registers as a match director', function () {
    Mail::fake();

    Livewire::test(Register::class)
        ->set('name', 'Dana Director')
        ->set('email', 'dana@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('wants_md', true)
        ->set('host_hint', 'Pretoria Rifle Club')
        ->call('register');

    Mail::assertQueued(NewRegistrationMail::class, 2);
    Mail::assertQueued(NewRegistrationMail::class, function (NewRegistrationMail $mail): bool {
        return $mail->hasTo('dirk@example.test')
            && $mail->registrant->email === 'dana@example.test'
            && $mail->roles === ['Shooter', 'Match director']
            && $mail->hostHint === 'Pretoria Rifle Club';
    });
    Mail::assertQueued(NewRegistrationMail::class, fn (NewRegistrationMail $mail): bool => $mail->hasTo('di@example.test'));

    $mail = new NewRegistrationMail(
        registrant: User::query()->where('email', 'dana@example.test')->firstOrFail(),
        roles: ['Shooter', 'Match director'],
        hostHint: 'Pretoria Rifle Club',
    );

    expect($mail->render())->toContain('Dana Director')->toContain('Pretoria Rifle Club');
});

it('includes the business name when the signup is also a supplier', function () {
    Mail::fake();

    Livewire::test(Register::class)
        ->set('name', 'Sue Supplier')
        ->set('email', 'sue@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->set('wants_supplier', true)
        ->set('business_name', 'Delmas Gun Shop')
        ->call('register');

    Mail::assertQueued(NewRegistrationMail::class, function (NewRegistrationMail $mail): bool {
        return $mail->hasTo('dirk@example.test')
            && $mail->roles === ['Shooter', 'Supplier']
            && $mail->businessName === 'Delmas Gun Shop';
    });
});

it('sends nothing when no notification addresses are configured', function () {
    Mail::fake();
    config()->set('registration.notify_emails', []);

    Livewire::test(Register::class)
        ->set('name', 'Sam Shooter')
        ->set('email', 'sam@example.test')
        ->set('password', 'longenoughpassword')
        ->set('password_confirmation', 'longenoughpassword')
        ->call('register');

    Mail::assertNothingQueued();
});
