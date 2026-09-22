<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign in</p>
                <h1>Log in</h1>
                <p>One login for shooters and match directors. We'll take you to the right place.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                <form wire:submit="authenticate" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model="email" required autofocus autocomplete="email" maxlength="255">
                        @error('email') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Password</span>
                        <input type="password" wire:model="password" required autocomplete="current-password" maxlength="255">
                        @error('password') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field" style="flex-direction:row;align-items:center;gap:10px">
                        <input type="checkbox" wire:model="remember" style="width:auto">
                        <span style="letter-spacing:0;text-transform:none;font-family:inherit;font-size:14px;color:inherit">Keep me signed in on this device</span>
                    </label>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="authenticate">
                        <span wire:loading.remove wire:target="authenticate">Log in</span>
                        <span wire:loading wire:target="authenticate">Logging in…</span>
                    </button>
                </form>

                @if (! config('coming-soon.enabled'))
                    <p style="margin-top:22px;color:var(--slate)">
                        New here?
                        <a href="{{ route('register') }}" style="text-decoration:underline">Create an account</a>. One login for shooters, match directors, clubs and suppliers.
                    </p>
                @else
                    <p style="margin-top:22px;color:var(--slate)">
                        Public sign-up opens at launch. For now this login is
                        for the build team only.
                    </p>
                @endif
            </div>
        </section>
    </main>
</div>
