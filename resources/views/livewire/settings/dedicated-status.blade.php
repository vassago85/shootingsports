<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap" style="max-width:820px">
                <p class="label">Account · Dedicated status</p>
                <h1>Dedicated status tracker</h1>
                <p class="lede">
                    Keep count of the matches that go toward your dedicated-shooter or activity renewal. We don't know your
                    accrediting body's rules, so you tell us the body, the renewal date, and how many activity-counting
                    matches you need between renewals.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px" role="status" aria-live="polite">
                        {{ session('status') }}
                    </div>
                @endif

                @if (! $user->isPro())
                    <div class="empty" style="margin-bottom:18px">
                        <p>
                            <b>The progress widget is a Pro feature.</b>
                            You can fill in your details now so they're ready the moment Pro opens.
                        </p>
                    </div>
                @endif

                <form wire:submit.prevent="save" class="prefs-form">
                    <div class="prefs-row">
                        <label style="display:grid;gap:6px">
                            <b>Accrediting body</b>
                            <p style="margin:0">The association that renews your dedicated status (e.g. SAHGCA, SAGA, SAPSA, SAPRF).</p>
                            <input type="text" wire:model="body" maxlength="120"
                                placeholder="SAHGCA"
                                style="padding:9px 12px;border:1px solid var(--rule);border-radius:8px;background:var(--surface);color:var(--ink);max-width:360px">
                            @error('body')<span style="color:#b00020;font-size:13px">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div class="prefs-row">
                        <label style="display:grid;gap:6px">
                            <b>Next renewal date</b>
                            <p style="margin:0">The date your dedicated status is next up for renewal.</p>
                            <input type="date" wire:model="renewalOn"
                                style="padding:9px 12px;border:1px solid var(--rule);border-radius:8px;background:var(--surface);color:var(--ink);max-width:220px">
                            @error('renewalOn')<span style="color:#b00020;font-size:13px">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div class="prefs-row">
                        <label style="display:grid;gap:6px">
                            <b>Activity-counting matches required</b>
                            <p style="margin:0">How many matches you must log that count toward activity requirements, per renewal cycle.</p>
                            <input type="number" min="1" max="365" wire:model="requiredCount"
                                placeholder="6"
                                style="padding:9px 12px;border:1px solid var(--rule);border-radius:8px;background:var(--surface);color:var(--ink);max-width:140px">
                            @error('requiredCount')<span style="color:#b00020;font-size:13px">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div class="prefs-actions" style="display:flex;gap:12px;flex-wrap:wrap">
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        @if ($user->dedicated_body || $user->dedicated_renewal_on || $user->dedicated_required_count)
                            <button type="button" class="btn ghost" wire:click="clear"
                                wire:confirm="Clear your dedicated-status settings?">
                                Clear
                            </button>
                        @endif
                        <a class="btn ghost" href="{{ route('my-log') }}">Back to my log</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
