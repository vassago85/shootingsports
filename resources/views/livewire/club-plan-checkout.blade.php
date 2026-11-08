<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Club subscription</p>
                <h1>{{ $organisation->name }}</h1>
                <p>Members, invoices, guest days, reports, on-site entries and the club embed. Uploading events stays free.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:720px">
                @if ($organisation->isPaid())
                    <p>This club's subscription is active.</p>
                @elseif (! $ready)
                    <p>Pricing is not set yet.</p>
                @else
                    <p>{{ $pricing['annual']['display'] }} or {{ $pricing['monthly']['display'] }}.</p>
                    <button type="button" wire:click="checkout">Continue to Paystack</button>
                @endif
                @if ($organisation->isPaid() && filled($organisation->paystack_subscription_code))
                    <button type="button" wire:click="cancel">Cancel the club subscription</button>
                @endif
            </div>
        </section>
    </main>
</div>
