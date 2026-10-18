<?php

namespace App\Http\Controllers;

use App\Enums\MembershipInvoiceStatus;
use App\Models\MembershipInvoice;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Support\PlatformFees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Self-service payment endpoints for the `/my-memberships` surface.
 * Keeps the full Paystack init out of the Livewire component (the
 * HTTP redirect to Paystack is cleaner from a controller than from a
 * component event) and keeps the "I've paid by EFT" nudge path in
 * one obvious place.
 */
class MembershipPaymentController extends Controller
{
    public function __construct(private readonly PaystackClient $client) {}

    public function paystack(Request $request, MembershipInvoice $invoice): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_if($user === null, 403);

        $this->authorizeMemberAccess($user, $invoice);

        if ($invoice->status !== MembershipInvoiceStatus::Issued) {
            return redirect()->route('my-memberships')
                ->with('status', 'That invoice is not open.');
        }

        $organisation = $invoice->organisation;

        if (! (bool) ($organisation?->membership_online_payments)) {
            return redirect()->route('my-memberships')
                ->with('status', 'This club is not accepting online payments right now.');
        }

        $split = PlatformFees::splitFor((int) $invoice->amount_cents);
        $transactionCharge = $split['fee'] + $split['fee_vat'];
        $hasSubaccount = filled($organisation->paystack_subaccount_id);

        $metadata = [
            'purpose' => 'club_membership',
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
        ];

        if ($hasSubaccount) {
            $metadata['platform_fee_cents'] = $split['fee'];
            $metadata['platform_fee_vat_cents'] = $split['fee_vat'];
        }

        try {
            $init = $this->client->initializeOneOffTransaction(
                email: (string) $user->email,
                amountCents: (int) $invoice->amount_cents,
                callbackUrl: route('paystack.callback'),
                metadata: $metadata,
                subaccount: $organisation->paystack_subaccount_id,
                transactionCharge: $hasSubaccount ? $transactionCharge : null,
                bearer: $hasSubaccount ? 'subaccount' : null,
            );
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Membership Paystack init failed', [
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('my-memberships')
                ->with('status', 'Could not start the payment. Try again in a moment, or pay by EFT.');
        }

        // Stamp the reference up-front so the webhook can resolve the
        // invoice even if the Checkout callback never fires (customer
        // closes the tab before being redirected back).
        $invoice->forceFill(['paystack_reference' => $init['reference']])->save();

        return redirect()->away($init['authorization_url']);
    }

    public function notifyEftPaid(Request $request, MembershipInvoice $invoice): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_if($user === null, 403);

        $this->authorizeMemberAccess($user, $invoice);

        if ($invoice->status !== MembershipInvoiceStatus::Issued) {
            return redirect()->route('my-memberships')
                ->with('status', 'That invoice is not open.');
        }

        $invoice->forceFill([
            'payment_pending_confirmation_at' => now(),
        ])->save();

        return redirect()->route('my-memberships.show', $invoice->clubMember)
            ->with('status', 'Thanks — the secretary has been notified.');
    }

    private function authorizeMemberAccess(User $user, MembershipInvoice $invoice): void
    {
        $member = $invoice->clubMember;

        abort_if($member === null, 404);

        $isLinked = $member->user_id === $user->id;
        $emailMatches = $member->email !== null && mb_strtolower($member->email) === mb_strtolower((string) $user->email);

        abort_unless($isLinked || $emailMatches, 403);
    }
}
