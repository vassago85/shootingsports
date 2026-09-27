<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Claim a listing</p>
                <h1>{{ $provider->name }}</h1>
                <p>{{ collect([$provider->town, $provider->province?->getLabel(), $provider->category?->getLabel()])->filter()->implode(' · ') }}</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if ($blocked === 'guest')
                    <div class="empty" style="border-color:var(--brass)">
                        <p style="margin-top:0">This listing is not claimed yet. Three steps:</p>
                        <ol style="margin:0 0 18px;padding-left:1.2rem;line-height:1.7">
                            <li>Create an account. Leave the match director and supplier boxes unticked so you come straight back to this page.</li>
                            <li>Tell us how you are connected to {{ $provider->name }}.</li>
                            <li>A site admin approves the claim before you can edit the page.</li>
                        </ol>
                        <p style="display:flex;flex-wrap:wrap;gap:10px;margin:0">
                            <a class="btn" href="{{ route('register') }}">Create an account</a>
                            <a class="btn ghost" href="{{ route('login') }}">I already have an account</a>
                        </p>
                    </div>
                @elseif ($blocked === 'pending')
                    <div class="empty" style="border-color:var(--brass)">
                        <p>Your claim is in for review. Staff will email you once it is decided. Until then the public page stays as it is.</p>
                    </div>
                @elseif ($blocked === 'taken')
                    <div class="empty">
                        <p>This listing already has an owner. If that is a mistake, <a href="{{ route('contact') }}">email us</a> and we will look at it.</p>
                    </div>
                @elseif ($blocked === 'already-listed')
                    <div class="empty">
                        <p>This account already has a business listing. <a href="{{ route('contact') }}">Email us</a> if this page should be yours instead.</p>
                    </div>
                @else
                    <form wire:submit="submit" class="enquiry-form" novalidate>
                        <p style="color:var(--slate);margin-top:0">Tell us how you are connected to this business. A role, a website, or a phone number that matches the listing is enough. Staff approve the claim before you can edit the page.</p>
                        <label class="field">
                            <span>Why is this your business? *</span>
                            <textarea wire:model="evidence" rows="5" required minlength="20" maxlength="2000" placeholder="I own the shop. The phone number on the listing is ours."></textarea>
                            @error('evidence') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="submit">
                            <span wire:loading.remove wire:target="submit">Submit claim</span>
                            <span wire:loading wire:target="submit">Submitting…</span>
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </main>
</div>
