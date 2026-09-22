<x-layouts.public
    title="Advertise on the register"
    description="Ask about advertising on shootingsports.co.za — the independent national register of South African shooting sport."
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Advertise</p>
                <h1>Advertise on the register</h1>
                <p>Shooting Sports is free to list on and free to browse. Advertising pays the bills. Every space on this page is labelled, none of them buy editorial ranking.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">Spaces</p>
                    <h2>Where an advert can sit</h2>
                </div>

                @unless ($industryOpen)
                    <p class="empty" style="margin-bottom:22px">
                        The Industry directory is still filling up.
                        Paid directory upgrades open once we have real free listings to sit above —
                        <a href="{{ route('claim') }}">claim a free listing</a> now to get in early.
                    </p>
                @endunless

                <div class="rate-card">
                    @foreach ($products as $product)
                        <article class="rate-card-row" id="prod-{{ $product['key'] }}">
                            <div class="rate-card-body">
                                <h3>{{ $product['name'] }}</h3>
                                <p>{{ $product['summary'] }}</p>
                                <p class="rate-card-audience">{{ $product['audience'] }}</p>
                                <a class="btn ghost" href="#enquire?product={{ $product['key'] }}" onclick="document.getElementById('advertise-product').value='{{ $product['key'] }}'; document.getElementById('advertise-product').dispatchEvent(new Event('change'));">Enquire</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">Our commitments</p>
                    <h2>What we will and will not do</h2>
                </div>
                <ul class="commitments">
                    @foreach ($commitments as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="block" id="enquire">
            <div class="wrap" style="max-width:560px">
                <div class="sec-head">
                    <p class="label">Enquire</p>
                    <h2>Ask about a space</h2>
                </div>

                @if ($errors->any())
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="post" action="{{ route('enquiries.store') }}" class="enquiry-form" novalidate>
                    @csrf
                    <input type="hidden" name="type" value="advertise">
                    <input type="hidden" name="form_loaded_at" value="{{ time() }}">

                    {{-- Honeypot: hidden from humans, bots often fill it. --}}
                    <div class="hp" aria-hidden="true">
                        <label for="company_website">Company website</label>
                        <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="field">
                        <span>Product</span>
                        <select name="product" id="advertise-product">
                            <option value="">Not sure — send me the options</option>
                            @foreach ($products as $product)
                                <option value="{{ $product['key'] }}">{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </label>

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
                        <span>What are you trying to achieve?</span>
                        <textarea name="body" rows="6" required minlength="10" maxlength="5000">{{ old('body') }}</textarea>
                    </label>
                    <button type="submit" class="btn">Send enquiry</button>
                </form>
            </div>
        </section>
    </main>
</x-layouts.public>
