<?php

use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
});

it('records a sent email so staff can read it in the admin log', function () {
    Mail::raw('Hello Dirk, Dana just registered.', function ($message): void {
        $message->to('dirkpio01@gmail.com')
            ->subject('New registration: Dana Director');
    });

    $log = EmailLog::query()->first();

    expect($log)->not->toBeNull()
        ->and($log->to)->toBe('dirkpio01@gmail.com')
        ->and($log->subject)->toBe('New registration: Dana Director')
        ->and($log->body)->toContain('Dana just registered');

    $staff = User::factory()->staff()->create();

    Livewire::actingAs($staff)
        ->test(ManageEmailLogs::class)
        ->assertCanSeeTableRecords([$log]);

    $this->actingAs($staff)
        ->get(EmailLogResource::getUrl('index'))
        ->assertOk()
        ->assertSee('New registration: Dana Director');
});

it('hides the email log from a shooter', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(EmailLogResource::getUrl('index'))
        ->assertForbidden();
});
