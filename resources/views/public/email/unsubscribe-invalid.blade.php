<x-layouts.public
    title="Unsubscribe link invalid — Shooting Sports"
    description="That unsubscribe link is invalid or has already been used."
    :robots="'noindex, nofollow'"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap" style="max-width:720px">
                <p class="label">Email preferences</p>
                <h1>That link isn't valid.</h1>
                <p class="lede">
                    The unsubscribe link you followed is invalid, expired, or has already been used.
                    If you want to stop receiving marketing emails, the fastest way is to sign in and open Notifications.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:720px">
                <div class="empty">
                    <p>
                        <a class="btn" href="{{ route('login') }}?intended={{ urlencode(route('settings.notifications')) }}">
                            Sign in to manage email preferences
                        </a>
                    </p>
                    <p style="margin-top:14px">
                        Or <a href="{{ route('contact') }}">email us</a> and we'll unsubscribe you manually — same day.
                    </p>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
