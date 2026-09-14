<x-layouts.public title="Embed the calendar" description="Embed the Shooting Sports match calendar on a club website.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">For clubs</p>
                <h1>Embed the calendar</h1>
                <p>Drop this script on a club site. It renders an iframe of upcoming matches. Optional <span class="mono">data-discipline</span> and <span class="mono">data-province</span> attributes filter the feed.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <pre class="mono" style="background:var(--surface);border:1px solid var(--rule);padding:18px;overflow:auto;font-size:13px">&lt;script src="{{ url('/embed/calendar.js') }}" data-discipline="precision-rifle"&gt;&lt;/script&gt;</pre>
                <p style="margin-top:28px" class="label">Preview</p>
                <iframe src="{{ route('embed.calendar') }}" title="Embedded match calendar" style="width:100%;min-height:520px;border:1px solid var(--rule);margin-top:12px"></iframe>
            </div>
        </section>
    </main>
</x-layouts.public>
