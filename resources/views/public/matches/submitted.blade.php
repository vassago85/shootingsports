<x-layouts.public title="Match submitted" description="Your match is in for review.">
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Match submitted</p>
                <h1>Thanks. This match is in for review</h1>
                <p>It stays off the public calendar until we approve it. You can submit another whenever you have a date.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                <dl class="dope-rows" style="max-width:520px;padding:0">
                    <div class="r"><dt>Match</dt><dd>{{ $event->title }}</dd></div>
                    <div class="r"><dt>Date</dt><dd>{{ $event->starts_at?->format('j M Y') }}</dd></div>
                    <div class="r"><dt>Host</dt><dd>{{ $event->hostOrganisation?->name }}</dd></div>
                    <div class="r"><dt>Status</dt><dd>Waiting for approval</dd></div>
                </dl>

                <p style="margin-top:22px">
                    <a class="btn" href="{{ route('matches.submit') }}">Submit another match</a>
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
