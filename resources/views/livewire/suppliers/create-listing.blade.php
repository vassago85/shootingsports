<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Supplier onboarding · Step 2 of 2</p>
                <h1>Register your services</h1>
                <p>Tell us about your business and the services you offer. Staff will review your listing, usually within one working day, before it goes live in the Industry directory.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                <form wire:submit="submit" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Business name</span>
                        <input type="text" wire:model="name" required autofocus autocomplete="organization" maxlength="160">
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <div class="field">
                        <label for="supplier-category"><span>Primary category *</span></label>
                        <select id="supplier-category" wire:model.live="category" wire:key="supplier-category" required>
                            <option value="">Pick one</option>
                            @foreach ($categoryOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            The main thing you do. This drives which directory landing your listing headlines.
                        </small>
                        @error('category') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    @if (! empty($serviceOptions))
                        <fieldset class="field" style="border:none;padding:0;margin:0">
                            <legend style="font-family:'Saira Condensed',sans-serif;text-transform:uppercase;letter-spacing:.08em;color:var(--slate);font-size:14px;margin-bottom:6px">Additional services (optional)</legend>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px 14px">
                                @foreach ($serviceOptions as $value => $label)
                                    <label style="display:flex;align-items:center;gap:8px;font-family:inherit;font-size:14px;color:inherit">
                                        <input type="checkbox" wire:model="services" value="{{ $value }}" style="width:auto">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('services') <span class="err">{{ $message }}</span> @enderror
                        </fieldset>
                    @endif

                    <div class="field">
                        <label for="supplier-province"><span>Province *</span></label>
                        <select id="supplier-province" wire:model.live="province" wire:key="supplier-province" required>
                            <option value="">Pick one</option>
                            @foreach ($provinceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('province') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <label class="field">
                        <span>Town</span>
                        <input type="text" wire:model="town" required maxlength="120" autocomplete="address-level2">
                        @error('town') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Public email (optional)</span>
                        <input type="email" wire:model="email" autocomplete="email" maxlength="255">
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            Shown behind an "Enquire" button on your public page. Never displayed in the clear.
                        </small>
                        @error('email') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Phone (optional)</span>
                        <input type="tel" wire:model="phone" autocomplete="tel" maxlength="40">
                        @error('phone') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Website URL (optional)</span>
                        <input type="url" wire:model="website_url" autocomplete="url" maxlength="255" placeholder="https://">
                        @error('website_url') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Describe your business *</span>
                        <textarea wire:model="description" rows="5" required minlength="20" maxlength="2000" placeholder="What you do, what you stock, what makes you worth listing. Aim for a paragraph."></textarea>
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            Between 20 and 2000 characters. Shown on your public directory page.
                        </small>
                        @error('description') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Submit for review</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</div>
