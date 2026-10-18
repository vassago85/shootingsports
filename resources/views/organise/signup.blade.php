<x-layouts.public
    title="Organise a single event"
    description="List one match on the register for a once-off fee. No club subscription needed."
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Organise</p>
                <h1>List a single event</h1>
                <p>
                    One match, one once-off fee of R{{ number_format($incl / 100, 2) }}
                    (R{{ number_format($excl / 100, 2) }} + R{{ number_format($vat / 100, 2) }} VAT).
                    Your event goes live as soon as the payment clears.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:560px">
                <form method="POST" action="{{ route('organise.submit') }}" class="enquiry-form" novalidate>
                    @csrf

                    <div class="reg-step">
                        <p class="reg-step-label"><span class="reg-step-num">1</span> Your account</p>
                        <label class="field">
                            <span>Your name</span>
                            <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name" maxlength="120">
                            @error('name') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255">
                            @error('email') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>Password (min 8 characters)</span>
                            <input type="password" name="password" required autocomplete="new-password" maxlength="255">
                            @error('password') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="reg-step">
                        <p class="reg-step-label"><span class="reg-step-num">2</span> Your event</p>
                        <label class="field">
                            <span>Event name</span>
                            <input type="text" name="event_name" value="{{ old('event_name') }}" required maxlength="160">
                            @error('event_name') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>Event date</span>
                            <input type="date" name="event_date" value="{{ old('event_date') }}" required>
                            @error('event_date') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>Venue city</span>
                            <input type="text" name="venue_city" value="{{ old('venue_city') }}" required maxlength="120">
                            @error('venue_city') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <button type="submit" class="btn">Continue to payment</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
