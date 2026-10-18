<?php

namespace App\Services\Platform;

use App\Models\AgencyAgreementAcceptance;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use RuntimeException;

class PaystackSubaccountService
{
    public function __construct(private PaystackClient $client) {}

    /**
     * Record agreement acceptance, create the Paystack Subaccount and
     * stamp the organisation. Returns the Paystack `subaccount_code`.
     */
    public function create(
        Organisation $organisation,
        User $acceptor,
        string $businessName,
        string $settlementBank,
        string $accountNumber,
        ?string $ipAddress = null,
    ): string {
        if ($organisation->paystack_subaccount_id !== null) {
            throw new RuntimeException('Organisation already has a Paystack subaccount.');
        }

        $version = AgencyAgreement::currentVersion();

        AgencyAgreementAcceptance::create([
            'organisation_id' => $organisation->id,
            'user_id' => $acceptor->id,
            'version' => $version,
            'ip_address' => $ipAddress,
        ]);

        $subaccount = $this->client->createSubaccount($businessName, $settlementBank, $accountNumber);

        $organisation->forceFill([
            'paystack_subaccount_id' => $subaccount['subaccount_code'],
            'paystack_subaccount_status' => $subaccount['is_verified'] ? 'active' : 'pending',
            'agency_agreement_signed_at' => now(),
            'agency_agreement_version' => $version,
        ])->save();

        return $subaccount['subaccount_code'];
    }
}
