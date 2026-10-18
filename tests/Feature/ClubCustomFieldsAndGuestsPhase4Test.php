<?php

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Models\ClubGuestVisit;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\User;
use App\Support\MembershipCustomFields;
use Illuminate\Support\Facades\Validator;

it('normalises the custom fields schema and drops entries without a key', function () {
    $organisation = Organisation::factory()->create([
        'membership_custom_fields' => [
            ['key' => 'sa_id', 'label' => 'SA ID', 'type' => 'text', 'required' => true],
            ['key' => 'diet', 'label' => 'Diet', 'type' => 'select', 'required' => false, 'options' => ['none', 'veg', 'halal']],
            ['label' => 'no key here', 'type' => 'text'],
            ['key' => 'birth_year', 'label' => 'Birth year', 'type' => 'number', 'required' => false],
            ['key' => 'funky', 'label' => 'Funky', 'type' => 'not-a-type'],
        ],
    ]);

    $schema = MembershipCustomFields::schemaFor($organisation);

    expect($schema)->toHaveCount(4)
        ->and($schema[0]['required'])->toBeTrue()
        ->and($schema[1]['options'])->toBe(['none', 'veg', 'halal'])
        ->and($schema[3]['type'])->toBe('text'); // bogus type folds back to text
});

it('builds Laravel rules for each field type with required / nullable prefix', function () {
    $organisation = Organisation::factory()->create([
        'membership_custom_fields' => [
            ['key' => 'sa_id', 'label' => 'SA ID', 'type' => 'text', 'required' => true],
            ['key' => 'diet', 'label' => 'Diet', 'type' => 'select', 'required' => false, 'options' => ['none', 'veg']],
            ['key' => 'birth_year', 'label' => 'Birth year', 'type' => 'number', 'required' => false],
            ['key' => 'consent', 'label' => 'Consent', 'type' => 'checkbox', 'required' => true],
        ],
    ]);

    $rules = MembershipCustomFields::rules($organisation);

    expect($rules)->toHaveKey('custom_attributes.sa_id')
        ->and($rules['custom_attributes.sa_id'])->toContain('required')
        ->and($rules['custom_attributes.sa_id'])->toContain('max:255')
        ->and($rules['custom_attributes.diet'])->toContain('in:none,veg')
        ->and($rules['custom_attributes.birth_year'])->toContain('numeric')
        ->and($rules['custom_attributes.consent'])->toContain('boolean');
});

it('rejects a submission that leaves a required custom field blank', function () {
    $organisation = Organisation::factory()->create([
        'membership_custom_fields' => [
            ['key' => 'sa_id', 'label' => 'SA ID', 'type' => 'text', 'required' => true],
        ],
    ]);

    $validator = Validator::make(
        ['custom_attributes' => ['sa_id' => '']],
        MembershipCustomFields::rules($organisation),
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('custom_attributes.sa_id'))->toBeTrue();
});

it('accepts a submission that provides all required custom fields', function () {
    $organisation = Organisation::factory()->create([
        'membership_custom_fields' => [
            ['key' => 'sa_id', 'label' => 'SA ID', 'type' => 'text', 'required' => true],
            ['key' => 'birth_year', 'label' => 'Birth year', 'type' => 'number', 'required' => false],
        ],
    ]);

    $validator = Validator::make(
        ['custom_attributes' => ['sa_id' => '9001011234088', 'birth_year' => 1990]],
        MembershipCustomFields::rules($organisation),
    );

    expect($validator->fails())->toBeFalse();
});

it('persists custom_attributes on a ClubMember as a JSON column', function () {
    $organisation = Organisation::factory()->create();

    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'custom_attributes' => ['sa_id' => '9001011234088', 'diet' => 'veg'],
    ]);

    $member->refresh();
    expect($member->custom_attributes)->toBe(['sa_id' => '9001011234088', 'diet' => 'veg']);
});

it('creates a ClubGuestVisit via factory and reports it as not-yet-converted', function () {
    $visit = ClubGuestVisit::factory()->create();

    expect($visit->hasBeenConverted())->toBeFalse()
        ->and($visit->visit_date)->not->toBeNull();
});

it('converts a guest visit to an Applied ClubMember and stamps the conversion', function () {
    $user = User::factory()->create();

    $organisation = Organisation::factory()->create();
    $visit = ClubGuestVisit::factory()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Walkup Guest',
        'email' => 'guest@example.test',
    ]);

    // Mirror what the Desk convertToApplication record action does
    // so we can prove the shape of the resulting ClubMember without
    // booting a full Filament page.
    $member = ClubMember::create([
        'organisation_id' => $visit->organisation_id,
        'first_name' => 'Walkup',
        'last_name' => 'Guest',
        'email' => $visit->email,
        'phone' => $visit->phone,
        'category' => ClubMemberCategory::Senior,
        'status' => ClubMemberStatus::Applied,
        'notes' => 'Converted from guest visit '.$visit->visit_date->format('j M Y'),
    ]);
    $member->statusChanges()->create([
        'from_status' => null,
        'to_status' => ClubMemberStatus::Applied->value,
        'changed_by' => $user->id,
        'reason' => 'Guest visit convert',
    ]);
    $visit->forceFill(['converted_club_member_id' => $member->id])->save();

    expect($visit->refresh()->hasBeenConverted())->toBeTrue()
        ->and($member->status)->toBe(ClubMemberStatus::Applied)
        ->and($member->statusChanges()->count())->toBe(1);
});
