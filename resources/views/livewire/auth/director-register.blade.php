<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign up · Match director</p>
                <h1>Register as a match director</h1>
                <p>Publish matches for a club, range, or host on Shooting Sports. Free to list. Staff review new accounts and pair you with existing organisations on the directory.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                <form wire:submit="register" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Your name</span>
                        <input type="text" wire:model="name" required autofocus autocomplete="name" maxlength="120">
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model="email" required autocomplete="email" maxlength="255">
                        @error('email') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Password (min 8 characters)</span>
                        <input type="password" wire:model="password" required autocomplete="new-password" maxlength="255">
                        @error('password') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Confirm password</span>
                        <input type="password" wire:model="password_confirmation" required autocomplete="new-password" maxlength="255">
                    </label>
                    <label class="field">
                        <span>Which club, range or host will you publish for? (optional)</span>
                        <textarea wire:model="host_hint" rows="3" maxlength="500" placeholder="e.g. Pretoria Rifle &amp; Pistol Club, or Muletech Ridge Range"></textarea>
                        @error('host_hint') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="register">
                        <span wire:loading.remove wire:target="register">Create match director account</span>
                        <span wire:loading wire:target="register">Creating…</span>
                    </button>
                </form>

                <p style="margin-top:22px;color:var(--slate)">
                    Already have an account?
                    <a href="{{ route('login') }}">Log in</a>.
                    Just want to save matches you're going to?
                    <a href="{{ route('register') }}">Create a shooter account instead</a>.
                </p>
            </div>
        </section>
    </main>
</div>
