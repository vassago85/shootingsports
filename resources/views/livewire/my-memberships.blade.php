<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Account</p>
                <h1>My memberships</h1>
                <p>Your club memberships, renewals and open invoices.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($memberships->isEmpty())
                    <div class="empty" data-test="no-memberships">
                        <p>You have no club memberships linked to your account yet.</p>
                        <p style="margin-top:8px">Apply to join a club from its public profile page.</p>
                        <p style="margin-top:14px"><a class="btn" href="{{ route('clubs.index') }}">Find a club</a></p>
                    </div>
                @else
                    <ul class="stack" style="list-style:none;padding:0;margin:0;gap:16px;display:grid" data-test="memberships-list">
                        @foreach ($memberships as $membership)
                            @php($openInvoiceCount = $membership->invoices->filter(fn ($i) => $i->isOutstanding())->count())
                            <li class="card" data-test="membership-row" style="padding:18px;border:1px solid var(--line);border-radius:8px">
                                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
                                    <div>
                                        <h2 style="margin:0;font-size:1.1rem" data-test="membership-name">{{ $membership->fullName() }}</h2>
                                        <p style="margin:4px 0 0">{{ $membership->organisation?->name }}</p>
                                        <p style="margin:4px 0 0;color:var(--muted)">
                                            {{ $membership->category->getLabel() }} · {{ $membership->effectiveStatus()->getLabel() }}
                                            @if ($membership->expires_at)
                                                · Expires {{ $membership->expires_at->format('j M Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <div style="text-align:right">
                                        @if ($openInvoiceCount > 0)
                                            <p style="margin:0 0 6px;color:var(--brass)" data-test="open-invoice-count">{{ $openInvoiceCount }} open invoice{{ $openInvoiceCount === 1 ? '' : 's' }}</p>
                                        @endif
                                        <a class="btn" href="{{ route('my-memberships.show', $membership) }}" data-test="open-membership">Open</a>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </main>
</div>
