<x-layouts.public
    :title="$heading"
    description="Send a message through the Shooting Sports platform. Staff receive every enquiry."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Contact</p>
                <h1>{{ $heading }}</h1>
                <p>{{ $intro }}</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:560px">
                @if ($errors->any())
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="post" action="{{ route('enquiries.store') }}" class="enquiry-form" novalidate>
                    @csrf
                    <input type="hidden" name="type" value="{{ $type->value }}">
                    <input type="hidden" name="form_loaded_at" value="{{ time() }}">
                    @if (! empty($about))
                        <input type="hidden" name="about_type" value="{{ $aboutType }}">
                        <input type="hidden" name="about_id" value="{{ $about->id }}">
                    @endif

                    {{-- Honeypot: hidden from humans, bots often fill it. --}}
                    <div class="hp" aria-hidden="true">
                        <label for="company_website">Company website</label>
                        <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="field">
                        <span>Your name</span>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="120">
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255">
                    </label>
                    <label class="field">
                        <span>Phone (optional)</span>
                        <input type="text" name="phone" value="{{ old('phone') }}" maxlength="40">
                    </label>
                    <label class="field">
                        <span>Subject (optional)</span>
                        <input type="text" name="subject" value="{{ old('subject') }}" maxlength="180">
                    </label>
                    <label class="field">
                        <span>Message</span>
                        <textarea name="body" rows="6" required minlength="10" maxlength="5000">{{ old('body') }}</textarea>
                    </label>
                    <button type="submit" class="btn">Send enquiry</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
