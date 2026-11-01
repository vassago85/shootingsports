<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign in</p>
                <h1>Reset your password</h1>
                <p>Enter the email on your account. If we find it, we will send a reset link.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                @if ($status !== '')
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px" role="status">
                        {{ $status }}
                    </div>
                @endif

                <form wire:submit="sendResetLink" class="enquiry-form" novalidate>
                    @error('email')
                        <p class="login-error" id="reset-email-error" role="alert">{{ $message }}</p>
                    @enderror
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model.blur="email" required autofocus autocomplete="username" autocapitalize="none" maxlength="255" @error('email') aria-invalid="true" aria-describedby="reset-email-error" @enderror>
                    </label>
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="sendResetLink">
                        <span wire:loading.remove wire:target="sendResetLink">Email reset link</span>
                        <span wire:loading wire:target="sendResetLink">Sending…</span>
                    </button>
                </form>

                <p style="margin-top:22px;color:var(--slate)">
                    <a href="{{ route('login') }}" style="text-decoration:underline">Back to sign in</a>
                </p>
            </div>
        </section>
    </main>
</div>
