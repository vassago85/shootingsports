<x-layouts.public title="Confirm your email" description="Confirm your email address to keep going.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Almost there</p>
                <h1>Confirm your email address</h1>
                <p>We sent a confirmation link to <b>{{ auth()->user()?->email }}</b>. Open it to activate your account. If it takes a while to arrive, check your junk folder, or request another link below.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                @if (session('status') === 'verification-link-expired')
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        That confirmation link has expired. Request another one and we will send a fresh link to your inbox.
                    </div>
                @elseif (session('status') === 'verification-link-sent')
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        A new confirmation link has been sent to your email address.
                    </div>
                @elseif (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn">Request another link</button>
                </form>

                <form method="POST" action="{{ route('logout') }}" style="margin-top:14px">
                    @csrf
                    <button type="submit" class="btn ghost">Sign out</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
