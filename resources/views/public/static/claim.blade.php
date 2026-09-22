<x-layouts.public title="For clubs &amp; match directors" description="Register a desk account, claim a club or series, and put your matches on the national calendar.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">For clubs</p>
                <h1>Match director desk</h1>
                <p>Create or claim a club or series, upload a logo, and manage your own matches. New listings stay private until staff publish them.</p>
                <p style="margin-top:22px;display:flex;flex-wrap:wrap;gap:12px">
                    <a class="btn" href="{{ route('login') }}">Director login</a>
                    <a class="btn ghost on-dark" href="{{ route('register') }}">Create an account</a>
                </p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:720px">
                <div class="sec-head">
                    <p class="label">How it works</p>
                    <h2>Three steps</h2>
                </div>
                <ol style="margin:0;padding-left:1.2rem;color:var(--slate);line-height:1.7">
                    <li style="margin-bottom:12px"><b style="color:var(--ink)">Register</b> at the desk. Free, no staff approval needed to sign up.</li>
                    <li style="margin-bottom:12px"><b style="color:var(--ink)">Create</b> a club or series, or <b style="color:var(--ink)">claim</b> one already on the register (claim needs staff approval).</li>
                    <li><b style="color:var(--ink)">Add matches</b> under your listings. You can be a director on several clubs and federations at once.</li>
                </ol>
            </div>
        </section>
    </main>
</x-layouts.public>
