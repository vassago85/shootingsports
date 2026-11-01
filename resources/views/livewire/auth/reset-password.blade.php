<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign in</p>
                <h1>Choose a new password</h1>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                <form wire:submit="resetPassword" class="enquiry-form" novalidate>
                    @error('email')
                        <p class="login-error" role="alert">{{ $message }}</p>
                    @enderror
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model.blur="email" required autocomplete="username" autocapitalize="none" maxlength="255" @error('email') aria-invalid="true" @enderror>
                    </label>
                    <label class="field">
                        <span>New password</span>
                        <input type="password" wire:model.blur="password" required autocomplete="new-password" maxlength="255" @error('password') aria-invalid="true" aria-describedby="new-password-error" @enderror>
                        @error('password') <span class="err" id="new-password-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Confirm password</span>
                        <input type="password" wire:model.blur="password_confirmation" required autocomplete="new-password" maxlength="255">
                    </label>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="resetPassword">
                        <span wire:loading.remove wire:target="resetPassword">Save password</span>
                        <span wire:loading wire:target="resetPassword">Saving…</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</div>
