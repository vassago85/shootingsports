<x-layouts.public title="Confirm your email" description="Confirm your email address to keep going.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Almost there</p>
                <h1>Confirm your email address</h1>
                <p>We just sent a confirmation link to <b>{{ auth()->user()?->email }}</b>. Click it to activate your account. If nothing lands in the next few minutes, check your junk folder or resend below.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                @if (session('status') && session('status') !== 'verification-link-sent')
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('status') === 'verification-link-sent')
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        A new confirmation link has been sent to your email address.
                    </div>
                @endif

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn">Resend the link</button>
                </form>

                <form method="POST" action="{{ route('logout') }}" style="margin-top:14px">
                    @csrf
                    <button type="submit" class="btn ghost">Sign out</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
