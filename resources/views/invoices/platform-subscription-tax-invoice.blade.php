{{-- TBD-pending-accountant: finalise company address, VAT number and footer wording with the appointed accountant before launch. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tax Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11pt; color: #111; }
        header { display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
        .totals { width: 50%; margin-left: auto; }
        .totals td { border: none; }
        .totals .label { text-align: right; }
        footer { margin-top: 30px; font-size: 9pt; color: #555; }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>TAX INVOICE</h1>
            <p><strong>shootingsports.co.za</strong></p>
            <p>{{ config('services.sars.company_address', 'TBD-pending-registration') }}</p>
            <p>VAT no. {{ config('services.sars.vat_number', 'TBD-pending-registration') }}</p>
        </div>
        <div>
            <p><strong>Invoice:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $invoice->issued_on->format('Y-m-d') }}</p>
            <p><strong>Paystack ref:</strong> {{ $invoice->paystack_reference }}</p>
        </div>
    </header>

    <section>
        <h2>Billed to</h2>
        <p><strong>{{ $invoice->organisation->name }}</strong></p>
        @if (filled($invoice->organisation->vat_number ?? null))
            <p>VAT no. {{ $invoice->organisation->vat_number }}</p>
        @endif
    </section>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th>Amount (excl. VAT)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->description }}</td>
                <td>{{ $invoice->period_start->format('Y-m-d') }} — {{ $invoice->period_end->format('Y-m-d') }}</td>
                <td>R{{ number_format($invoice->amount_excl_vat_cents / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Subtotal (excl. VAT):</td><td>R{{ number_format($invoice->amount_excl_vat_cents / 100, 2) }}</td></tr>
        <tr><td class="label">VAT ({{ rtrim(rtrim(number_format($invoice->vat_bps / 100, 2), '0'), '.') }}%):</td><td>R{{ number_format($invoice->vat_cents / 100, 2) }}</td></tr>
        <tr><td class="label"><strong>Total (incl. VAT):</strong></td><td><strong>R{{ number_format($invoice->amount_incl_vat_cents / 100, 2) }}</strong></td></tr>
    </table>

    <footer>
        <p>Payment received via Paystack. Reference: {{ $invoice->paystack_reference }}.</p>
        <p>This is a tax invoice issued under the VAT Act of 1991.</p>
    </footer>
</body>
</html>
