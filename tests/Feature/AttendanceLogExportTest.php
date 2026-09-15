<?php

use App\Enums\Plan;
use App\Models\AttendedEvent;
use App\Models\User;

it('CSV export is 403 for Free users', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    $this->actingAs($user)
        ->get('/my-log/export/csv?year='.now()->year)
        ->assertForbidden();
});

it('CSV export streams a valid CSV for Pro users with the right columns', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'Test Match',
        'event_date' => now()->format('Y-m-d'),
        'discipline_name_snapshot' => 'IPSC Handgun',
        'host_snapshot' => 'Test Club',
        'division' => 'Standard',
        'placing' => 5,
        'field_size' => 30,
    ]);

    $response = $this->actingAs($user)
        ->get('/my-log/export/csv?year='.now()->year);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition');

    $body = $response->streamedContent();

    // fputcsv quotes any field containing a space, hence "Field size".
    expect($body)
        ->toContain('Date,Match,Discipline,Host,Venue,Division,Class,Placing,"Field size",Score,Notes')
        ->toContain('Test Match')
        ->toContain('IPSC Handgun')
        ->toContain('Test Club')
        ->toContain('Standard');
});

it('print/PDF page is 403 for Free users', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    $this->actingAs($user)
        ->get('/my-log/print?year='.now()->year)
        ->assertForbidden();
});

it('print/PDF page renders for Pro with signature line and entries', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'name' => 'Test Shooter',
        'association_membership_number' => 'SAPSA-12345',
    ]);

    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'National Championship',
        'event_date' => now()->format('Y-m-d'),
        'discipline_name_snapshot' => 'IPSC Rifle',
    ]);

    $this->actingAs($user)
        ->get('/my-log/print?year='.now()->year)
        ->assertOk()
        ->assertSee('Annual attendance record')
        ->assertSee('Test Shooter')
        ->assertSee('SAPSA-12345')
        ->assertSee('National Championship')
        ->assertSee('IPSC Rifle')
        ->assertSee('counter-signed by')
        ->assertSee('Save as PDF', false); // instruction bar
});

it('print page renders "No matches logged" empty state for Pro with no entries', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    $this->actingAs($user)
        ->get('/my-log/print?year='.now()->year)
        ->assertOk()
        ->assertSee('No matches logged for '.now()->year);
});

it('CSV export omits entries from other years', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'This Year Match',
        'event_date' => now()->format('Y-m-d'),
    ]);
    AttendedEvent::factory()->create([
        'user_id' => $user->id,
        'event_name_snapshot' => 'Old Match',
        'event_date' => '2023-05-10',
    ]);

    $body = $this->actingAs($user)
        ->get('/my-log/export/csv?year='.now()->year)
        ->streamedContent();

    expect($body)
        ->toContain('This Year Match')
        ->not->toContain('Old Match');
});
