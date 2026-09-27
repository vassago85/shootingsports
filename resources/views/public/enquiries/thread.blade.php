<x-layouts.public
    title="Your enquiry"
    description="Read the reply from Shooting Sports and answer on this page."
    robots="noindex, nofollow"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Enquiry</p>
                <h1>{{ $enquiry->subject ?: 'Your enquiry' }}</h1>
                <p>Messages here stay with Shooting Sports. You can answer below.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:560px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ $errors->first() }}
                    </div>
                @endif

                <article class="listing">
                    <p class="meta">{{ $enquiry->name }} · {{ $enquiry->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</p>
                    <p style="white-space:pre-wrap;margin-top:10px">{{ $enquiry->body }}</p>
                </article>

                @foreach ($enquiry->replies as $reply)
                    <article class="listing">
                        <p class="meta">{{ $reply->from_enquirer ? $enquiry->name : 'Shooting Sports' }} · {{ $reply->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}</p>
                        <p style="white-space:pre-wrap;margin-top:10px">{{ $reply->body }}</p>
                    </article>
                @endforeach

                <form method="post" action="{{ route('enquiries.thread.reply', $enquiry->reply_token) }}" class="enquiry-form" style="margin-top:22px" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true">
                        <label for="company_website">Company website</label>
                        <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
                    </div>
                    <label class="field">
                        <span>Your reply</span>
                        <textarea name="body" rows="6" required minlength="2" maxlength="5000">{{ old('body') }}</textarea>
                    </label>
                    <button type="submit" class="btn">Send reply</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
