<?php

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\User;
use App\Services\ClubMembers\ImportClubMembers;

function csvFixture(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'cm-').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, [
        'member_number', 'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'category', 'status', 'joined_at', 'expires_at',
        'emergency_contact_name', 'emergency_contact_phone',
    ]);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

it('creates members on the first import', function () {
    $club = Organisation::factory()->create();
    $actor = User::factory()->staff()->create();

    $path = csvFixture([
        ['M001', 'Jana', 'van der Merwe', 'jana@example.com', '082 111 1111', '1990-04-12', 'senior', 'active', '2020-01-01', '2027-01-01', 'Pieter', '082 222 2222'],
        ['M002', 'Thabo', 'Mahlangu', 'thabo@example.com', '', '', 'junior', 'active', '', '', '', ''],
    ]);

    $result = (new ImportClubMembers)->handle($club, $path, $actor);

    expect($result->created)->toBe(2)
        ->and($result->updated)->toBe(0)
        ->and($result->skipped)->toBe(0)
        ->and(ClubMember::query()->count())->toBe(2)
        ->and(ClubMember::query()->where('member_number', 'M002')->sole()->category)->toBe(ClubMemberCategory::Junior);

    unlink($path);
});

it('updates matching rows on a second import instead of duplicating', function () {
    $club = Organisation::factory()->create();
    $actor = User::factory()->staff()->create();
    $importer = new ImportClubMembers;

    $path = csvFixture([
        ['M001', 'Jana', 'van der Merwe', 'jana@example.com', '082 000 0000', '', 'senior', 'active', '', '', '', ''],
    ]);

    $importer->handle($club, $path, $actor);

    // Second file: same member number, updated phone and expiry.
    $second = csvFixture([
        ['M001', 'Jana', 'van der Merwe', 'jana@example.com', '082 111 2222', '', 'senior', 'active', '', '2028-01-01', '', ''],
    ]);

    $result = $importer->handle($club, $second, $actor);

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(1)
        ->and(ClubMember::query()->count())->toBe(1);

    $member = ClubMember::query()->sole();
    expect($member->phone)->toBe('082 111 2222')
        ->and($member->expires_at->toDateString())->toBe('2028-01-01');

    unlink($path);
    unlink($second);
});

it('reports invalid rows but imports the rest', function () {
    $club = Organisation::factory()->create();

    $path = csvFixture([
        ['M001', 'Jana', 'van der Merwe', 'jana@example.com', '', '', 'senior', 'active', '', '', '', ''],
        ['M002', '', '', 'bad@example.com', '', '', 'senior', 'active', '', '', '', ''], // missing name
        ['M003', 'Thabo', 'Mahlangu', 'thabo@example.com', '', 'not-a-date', 'senior', 'active', '', '', '', ''],
    ]);

    $result = (new ImportClubMembers)->handle($club, $path);

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(2)
        ->and(count($result->errors))->toBe(2)
        ->and(ClubMember::query()->count())->toBe(1);

    unlink($path);
});

it('writes a status-change row for each imported member', function () {
    $club = Organisation::factory()->create();

    $path = csvFixture([
        ['M001', 'Jana', 'van der Merwe', 'jana@example.com', '', '', 'senior', 'active', '', '', '', ''],
    ]);

    (new ImportClubMembers)->handle($club, $path);

    $member = ClubMember::query()->sole();
    expect($member->statusChanges()->count())->toBe(1)
        ->and($member->statusChanges()->sole()->to_status)->toBe(ClubMemberStatus::Active);

    unlink($path);
});
