<x-layouts.public
    title="Unsubscribed — Shooting Sports"
    description="You will not receive marketing emails from Shooting Sports."
    :robots="'noindex, nofollow'"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap" style="max-width:720px">
                <p class="label">Email preferences</p>
                <h1>You're unsubscribed.</h1>
                <p class="lede">
                    We won't send you any more marketing emails at <b>{{ $user->email }}</b>.
                    You'll still get account emails (password resets, receipts, MD application updates, enquiry replies).
                    Those are how the service works.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:720px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="empty" style="margin-bottom:18px">
                    <p><b>Want to tune what you receive, not silence everything?</b></p>
                    <p style="margin-top:8px">
                        Sign in and open Notifications to keep the useful ones (new match alerts, trial reminders)
                        and drop just the marketing / product updates.
                    </p>
                    <p style="margin-top:14px">
                        <a class="btn" href="{{ route('login') }}?intended={{ urlencode(route('settings.notifications')) }}">Sign in to manage preferences</a>
                    </p>
                </div>

                <div class="empty">
                    <p><b>Unsubscribed by accident?</b></p>
                    <p style="margin-top:8px">One click puts you back on the marketing list. Your per-category preferences aren't changed.</p>
                    <form method="post" action="{{ route('email.resubscribe', ['token' => $user->unsubscribe_token]) }}" style="margin-top:14px">
                        @csrf
                        <button type="submit" class="btn ghost">Resubscribe</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
