<?php

use App\Models\AgencyAgreementAcceptance;
use App\Models\Organisation;
use App\Services\Platform\AgencyAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports a club as not having signed when no acceptance exists', function () {
    $org = Organisation::factory()->create();

    expect($org->hasSignedCurrentAgencyAgreement())->toBeFalse();
});

it('reports a club as signed once the current-version acceptance exists', function () {
    $org = Organisation::factory()->create();
    AgencyAgreementAcceptance::factory()->for($org)->create(['version' => AgencyAgreement::currentVersion()]);

    expect($org->fresh()->hasSignedCurrentAgencyAgreement())->toBeTrue();
});
