<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label"><a href="{{ route('my-memberships') }}" style="color:inherit">My memberships</a></p>
                <h1>{{ $member->organisation?->name }}</h1>
                <p>
                    {{ $member->category->getLabel() }} · {{ $member->effectiveStatus()->getLabel() }}
                    @if ($member->expires_at)
                        · Expires {{ $member->expires_at->format('j M Y') }}
                    @endif
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if (filled($flash))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px" data-test="flash">
                        {{ $flash }}
                    </div>
                @endif

                <h2 style="margin-top:0">Open invoices</h2>

                @php($openInvoices = $invoices->filter(fn ($i) => $i->isOutstanding()))

                @if ($openInvoices->isEmpty())
                    <div class="empty" data-test="no-open-invoices">You have nothing outstanding.</div>
                @else
                    <ul class="stack" style="list-style:none;padding:0;margin:0;gap:12px;display:grid" data-test="open-invoices">
                        @foreach ($openInvoices as $invoice)
                            <li class="card" data-test="open-invoice" style="padding:16px;border:1px solid var(--line);border-radius:8px">
                                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
                                    <div>
                                        <strong>R{{ number_format($invoice->amount_cents / 100, 2) }}</strong>
                                        <span style="color:var(--muted)">
                                            · {{ $invoice->period_start->format('j M Y') }} – {{ $invoice->period_end->format('j M Y') }}
                                        </span>
                                        @if ($invoice->payment_pending_confirmation_at)
                                            <p style="margin:6px 0 0;color:var(--brass)" data-test="pending-confirmation">Awaiting secretary confirmation.</p>
                                        @endif
                                    </div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                                        @if ($onlinePayEnabled)
                                            <form method="POST" action="{{ route('my-memberships.paystack', $invoice) }}">
                                                @csrf
                                                <button type="submit" class="btn" data-test="pay-online">Pay online</button>
                                            </form>
                                        @endif
                                        <button
                                            type="button"
                                            class="btn btn-ghost"
                                            data-test="notify-eft"
                                            wire:click="notifyPaidByEft({{ $invoice->id }})">I've paid by EFT</button>
                                    </div>
                                </div>
                                @if (filled($member->organisation?->membership_eft_instructions))
                                    <details style="margin-top:10px">
                                        <summary style="cursor:pointer">EFT details</summary>
                                        <pre style="white-space:pre-wrap;margin:8px 0 0">{{ $member->organisation->membership_eft_instructions }}</pre>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h2 style="margin-top:28px">Contact details</h2>

                <form wire:submit.prevent="updateContact" class="stack" style="display:grid;gap:12px;max-width:520px">
                    <label>
                        <span>Phone</span>
                        <input type="tel" wire:model.blur="phone" required maxlength="40">
                    </label>
                    <label>
                        <span>Emergency contact name</span>
                        <input type="text" wire:model.blur="emergencyContactName" required maxlength="120">
                    </label>
                    <label>
                        <span>Emergency contact phone</span>
                        <input type="tel" wire:model.blur="emergencyContactPhone" required maxlength="40">
                    </label>
                    <div>
                        <button type="submit" class="btn" data-test="save-contact">Save contact details</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
