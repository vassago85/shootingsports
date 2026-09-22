<x-layouts.public
    title="Off the plate — page not found"
    description="This URL does not exist on the register. Try the calendar, disciplines, clubs, or ranges instead."
    :robots="'noindex, nofollow'"
>
    <main id="main">
        <section class="error-hero">
            <div class="wrap">
                <div class="error-mark">
                    <svg viewBox="0 0 40 40" aria-hidden="true">
                        <circle cx="20" cy="20" r="17" fill="none" stroke="#879A4A" stroke-width="1.6"/>
                        <circle cx="20" cy="20" r="7.5" fill="none" stroke="#879A4A" stroke-width="1"/>
                        <circle cx="20" cy="20" r="3" fill="none" stroke="#879A4A" stroke-width=".9"/>
                        <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#879A4A" stroke-width="1.6"/>
                    </svg>
                    <span class="error-mark-code">404</span>
                </div>
                <p class="label">Off the plate</p>
                <h1>This page is not in the register.</h1>
                <p class="lede">
                    Listings on Shooting Sports never move and never 404. This is probably a mistyped URL or an old link.
                    Try one of the routes below, or start from the calendar.
                </p>

                <form action="{{ route('calendar') }}" method="get" class="error-search" role="search">
                    <label for="err-q" class="sr-only">Search the calendar</label>
                    <input
                        id="err-q"
                        type="search"
                        name="q"
                        placeholder="Search matches, clubs, disciplines…"
                        autocomplete="off"
                    >
                    <button type="submit" class="btn">Search the calendar</button>
                </form>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:900px">
                <ul class="error-links">
                    <li>
                        <a href="{{ route('calendar') }}">
                            <span class="k">01</span>
                            <span class="t">Match calendar</span>
                            <span class="d">Every published match, filterable by discipline, province, level.</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('disciplines.index') }}">
                            <span class="k">02</span>
                            <span class="t">Discover disciplines</span>
                            <span class="d">Every shooting discipline on the register, with next-match links.</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('clubs.index') }}">
                            <span class="k">03</span>
                            <span class="t">Clubs &amp; series</span>
                            <span class="d">Membership clubs, associations, branded match series.</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('ranges.index') }}">
                            <span class="k">04</span>
                            <span class="t">Ranges</span>
                            <span class="d">The physical venues where matches actually happen.</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('claim') }}">
                            <span class="k">05</span>
                            <span class="t">For clubs</span>
                            <span class="d">Claim your listing or add your club to the register.</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('contact') }}">
                            <span class="k">06</span>
                            <span class="t">Something is broken</span>
                            <span class="d">If a link brought you here that shouldn't have, let us know.</span>
                        </a>
                    </li>
                </ul>
            </div>
        </section>
    </main>
</x-layouts.public>
