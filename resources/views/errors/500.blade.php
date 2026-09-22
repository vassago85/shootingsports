<x-layouts.public
    title="Something jammed — internal error"
    description="An internal error stopped this page from loading. Try again in a moment or head back to the calendar."
    :robots="'noindex, nofollow'"
>
    <main id="main">
        <section class="error-hero">
            <div class="wrap">
                <div class="error-mark">
                    <svg viewBox="0 0 40 40" aria-hidden="true">
                        <circle cx="20" cy="20" r="17" fill="none" stroke="#879A4A" stroke-width="1.6"/>
                        <circle cx="20" cy="20" r="7.5" fill="none" stroke="#879A4A" stroke-width="1"/>
                        <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#879A4A" stroke-width="1.6"/>
                    </svg>
                    <span class="error-mark-code">500</span>
                </div>
                <p class="label">Malfunction</p>
                <h1>Something on our side jammed.</h1>
                <p class="lede">
                    This is on us, not you. The error has been logged and we will look at it. Give it a moment and try again, or head back to the calendar and keep browsing.
                </p>
                <p style="margin-top:24px">
                    <a class="btn" href="{{ route('calendar') }}">Back to the calendar</a>
                    <a class="btn ghost" href="{{ route('home') }}" style="margin-left:8px">Home</a>
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
