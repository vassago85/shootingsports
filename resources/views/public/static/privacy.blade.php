<x-layouts.public title="Privacy & POPIA" description="How shootingsports.co.za handles personal information, analytics and third-party services.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Legal</p>
                <h1>Privacy &amp; POPIA</h1>
                <p>Public listings show club, range and supplier contact details that the organisation chose to publish. We do not sell personal information.</p>
                <p>Last updated: 25 November 2026.</p>
            </div>
        </section>

        <section class="rail">
            <div class="wrap prose">
                <h2>Analytics</h2>
                <p>We count visits in two ways.</p>
                <p>A self-hosted, first-party install of <a href="https://umami.is" target="_blank" rel="noopener">Umami</a> records anonymous pageviews. That install sets no cookies and collects no personal identifiers. Those counts stay on our infrastructure.</p>
                <p>Public pages also load <a href="https://tagmanager.google.com/" target="_blank" rel="noopener">Google Tag Manager</a> (container <code>GTM-P4VMCZ6G</code>). Tags in that container, including Google Analytics where we enable it, can set cookies and send usage information to Google: pages viewed, the referring site, general location derived from the IP address, and device and browser details. Google processes that information to measure how the site is used. We do not sell personal information.</p>

                <h2>Cookies</h2>
                <p>Google Tag Manager and the tags it loads may set analytics cookies on public pages, including cookies whose names start with <code>_ga</code>. You can block or delete cookies in your browser; measurement is then less complete. Signed-in operators using the <code>/desk</code> or <code>/admin</code> surfaces also receive a strictly-necessary session cookie for authentication. That session cookie is not an advertising cookie.</p>

                <h2>Embedded calendars</h2>
                <p>The <code>/embed/calendar</code> iframe does not load Umami or Google Tag Manager. It is intentionally tracker-free so club sites that embed the register do not double-count their own visitors.</p>

                <h2>Search &amp; social crawlers</h2>
                <p>Google Search Console and standard search engines are welcome. The <code>/llms.txt</code> file publishes a curated map of the public pages for large-language-model crawlers; nothing behind <code>/desk</code> or <code>/admin</code> is a source.</p>

                <h2>Deleting your account</h2>
                <p>Signed-in shooters can delete the account from inside the mobile app (Me &rarr; Delete account) or from <code>/me/settings</code> on the website. Both routes do the same thing: the account is soft-deleted immediately &mdash; profile, saved matches, follows, attendance log, device tokens, and personal access tokens are hidden and the session is signed out &mdash; and a scheduled job hard-deletes every row thirty (30) days later. Within that window you can email <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a> from the same address to restore the account.</p>
                <p>Accounts that manage a club, shop or range, or that are approved match directors, must hand those listings over before deletion. The app and the website block the action and list what needs to be transferred.</p>
                <p>A narrow set of records may survive the purge where South African law requires it &mdash; for example invoice and tax records for a past Pro subscription or a Paystack entry payment. Those are kept only for the relevant retention period and are not used for any other purpose.</p>

                <h2>Contact</h2>
                <p>Data requests, corrections and takedown notices go to <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a>. A fuller POPIA notice will accompany user accounts in the next release.</p>
            </div>
        </section>
    </main>
</x-layouts.public>
