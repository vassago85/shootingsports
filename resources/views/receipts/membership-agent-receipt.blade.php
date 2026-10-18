{{-- TBD: finalise this wording with the appointed accountant/lawyer before launch. --}}
<article class="agent-receipt">
    <h1>Receipt from {{ $organisation->name }}</h1>
    <p>Collected on behalf of {{ $organisation->name }} by shootingsports.co.za (VAT no. {{ config('services.sars.vat_number', 'TBD-pending-registration') }}), acting as collection agent under Section 54 of the VAT Act, 1991.</p>

    <h2>Breakdown</h2>
    <table>
        <tr><td>Membership fee (to the club)</td><td>R{{ number_format(($invoice->amount_cents - $invoice->platform_fee_cents - $invoice->platform_fee_vat_cents) / 100, 2) }}</td></tr>
        <tr><td>Platform fee (shootingsports.co.za)</td><td>R{{ number_format($invoice->platform_fee_cents / 100, 2) }}</td></tr>
        <tr><td>VAT on platform fee ({{ \App\Support\PlatformFees::vatBps() / 100 }}%)</td><td>R{{ number_format($invoice->platform_fee_vat_cents / 100, 2) }}</td></tr>
        <tr><th>Total paid</th><th>R{{ number_format($invoice->amount_cents / 100, 2) }}</th></tr>
    </table>

    <p class="footer-note">Reference: {{ $invoice->paystack_reference }}</p>
    <p class="footer-note">Date: {{ optional($invoice->paid_at)->format('Y-m-d H:i') }}</p>
</article>
