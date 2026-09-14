<x-layouts.public title="Embed the calendar" description="Embed a club, range or nationwide Shooting Sports calendar on your website — including WordPress.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">For clubs &amp; ranges</p>
                <h1>Embed the calendar</h1>
                <p>One calendar engine. Clubs, federations and ranges can show their upcoming matches on their own site. WordPress gets a paste-the-URL embed; other sites can use an iframe or a short script.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <p class="label">For WordPress</p>
                <p style="max-width:40rem">Paste this URL on its own line in a post or page. WordPress turns it into the calendar. In Elementor or the Custom HTML block, paste the iframe instead — WordPress often strips <code>&lt;script&gt;</code> tags.</p>
                <pre class="mono embed-code">{{ url('/embed/calendar') }}?club=your-club-slug</pre>
                <pre class="mono embed-code" style="margin-top:12px">&lt;iframe
  src="{{ url('/embed/calendar') }}?club=your-club-slug"
  title="Match calendar"
  width="800"
  height="520"
  frameborder="0"
  scrolling="no"
  style="width:100%;min-height:520px;border:0;"&gt;&lt;/iframe&gt;</pre>

                <p class="label" style="margin-top:28px">Range</p>
                <pre class="mono embed-code">{{ url('/embed/calendar') }}?venue=your-range-slug</pre>

                <p class="label" style="margin-top:28px">Federation</p>
                <pre class="mono embed-code">{{ url('/embed/calendar') }}?organisation=your-federation-slug</pre>

                <p class="label" style="margin-top:28px">Shooter</p>
                <p style="max-width:40rem">Signed-in shooters add matches to <a href="{{ url('/my-calendar') }}">My calendar</a>, then embed that list:</p>
                <pre class="mono embed-code">{{ url('/embed/calendar') }}?shooter=your-shooter-slug</pre>

                <p class="label" style="margin-top:28px">Colours and fonts</p>
                <pre class="mono embed-code">{{ url('/embed/calendar') }}?club=your-club-slug&amp;theme=dark&amp;accent=%23D9AE52&amp;bg=%23131718&amp;ink=%23e8eae6&amp;font=inter&amp;height=640</pre>
                <p class="meta" style="margin:12px 0 0;font-family:var(--f-mono);font-size:13px;color:var(--slate)">
                    <b>theme</b> light or dark.
                    <b>accent</b>, <b>bg</b>, <b>ink</b> are hex colours.
                    <b>font</b> is ibm-plex, inter, source-sans, roboto, open-sans, saira or system.
                    <b>discipline</b> and <b>province</b> still filter the nationwide feed.
                </p>

                <p class="label" style="margin-top:28px">Other websites</p>
                <p style="max-width:40rem">If the CMS allows scripts, this tag builds the same iframe:</p>
                <pre class="mono embed-code">&lt;script src="{{ url('/embed/calendar.js') }}" data-club="your-club-slug"&gt;&lt;/script&gt;</pre>

                <p style="margin-top:28px" class="label">Preview</p>
                <iframe src="{{ route('embed.calendar') }}" title="Embedded match calendar" style="width:100%;min-height:520px;border:1px solid var(--rule);margin-top:12px"></iframe>
            </div>
        </section>
    </main>
</x-layouts.public>
