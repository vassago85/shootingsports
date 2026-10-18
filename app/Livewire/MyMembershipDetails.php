<?php

namespace App\Livewire;

use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shooter self-service for a single membership record. Shows open
 * invoices, offers the two renewal paths (Paystack checkout when
 * the club has online pay enabled, or an "I've paid by EFT" nudge
 * otherwise) and lets the member keep contact + emergency details
 * fresh without needing the secretary to touch the Desk.
 */
#[Layout('components.layouts.public', ['title' => 'My membership'])]
class MyMembershipDetails extends Component
{
    public ClubMember $member;

    public string $phone = '';

    public string $emergencyContactName = '';

    public string $emergencyContactPhone = '';

    public string $flash = '';

    public function mount(ClubMember $member): void
    {
        $this->authorizeAccess($member);
        $this->member = $member;
        $this->syncLocalState();
    }

    public function updateContact(): void
    {
        $data = $this->validate([
            'phone' => ['required', 'string', 'max:40'],
            'emergencyContactName' => ['required', 'string', 'max:120'],
            'emergencyContactPhone' => ['required', 'string', 'max:40'],
        ]);

        $this->member->forceFill([
            'phone' => $data['phone'],
            'emergency_contact_name' => $data['emergencyContactName'],
            'emergency_contact_phone' => $data['emergencyContactPhone'],
        ])->save();

        $this->flash = 'Contact details saved.';
    }

    public function notifyPaidByEft(int $invoiceId): void
    {
        $invoice = $this->openInvoiceForThisMember($invoiceId);

        if ($invoice === null) {
            return;
        }

        // We stamp the "pending confirmation" clock but keep status
        // at Issued — the secretary still has to tick it off against
        // the bank statement. Avoids a shooter marking themselves as
        // Paid without the money actually landing.
        $invoice->forceFill([
            'payment_pending_confirmation_at' => now(),
        ])->save();

        $this->flash = 'Thanks — we have told the secretary you have paid.';
        $this->member->refresh()->load('invoices');
    }

    public function render(): View
    {
        return view('livewire.my-membership-details', [
            'member' => $this->member,
            'invoices' => $this->member->invoices()->orderBy('period_start', 'desc')->get(),
            'onlinePayEnabled' => (bool) $this->member->organisation?->membership_online_payments,
        ]);
    }

    private function openInvoiceForThisMember(int $invoiceId): ?MembershipInvoice
    {
        /** @var MembershipInvoice|null $invoice */
        $invoice = MembershipInvoice::query()
            ->where('id', $invoiceId)
            ->where('club_member_id', $this->member->id)
            ->first();

        if ($invoice === null) {
            return null;
        }

        if ($invoice->status !== MembershipInvoiceStatus::Issued) {
            return null;
        }

        return $invoice;
    }

    private function authorizeAccess(ClubMember $member): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        abort_if($user === null, 403);

        $isLinked = $member->user_id === $user->id;
        $emailMatches = $member->email !== null && mb_strtolower($member->email) === mb_strtolower((string) $user->email);

        abort_unless($isLinked || $emailMatches, 403);
    }

    private function syncLocalState(): void
    {
        $this->phone = (string) $this->member->phone;
        $this->emergencyContactName = (string) $this->member->emergency_contact_name;
        $this->emergencyContactPhone = (string) $this->member->emergency_contact_phone;
    }
}
