<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My business</p>
                <h1>Update {{ $provider->name }}</h1>
                <p>Change the name, contact details, and what you offer. Category, province, and town still go through staff.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                <form wire:submit="save" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Business name</span>
                        <input type="text" wire:model="name" required maxlength="160" autocomplete="organization">
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <div class="field">
                        <span>Primary category</span>
                        <p style="margin:6px 0 0">{{ $provider->category?->getLabel() }}</p>
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
                        <span>Province and town</span>
                        <p style="margin:6px 0 0">{{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }}</p>
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            <a href="{{ route('contact') }}">Email us</a> to change these.
                        </small>
                    </div>

                    <label class="field">
                        <span>Public email (optional)</span>
                        <input type="email" wire:model="email" autocomplete="email" maxlength="255">
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
                        <span>Logo</span>
                        @if ($provider->logoUrl() && ! $logo)
                            <img class="supplier-logo-preview" src="{{ $provider->logoUrl() }}" alt="{{ $provider->name }} logo">
                        @endif
                        <input type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp">
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            Square PNG, JPG, or WebP. Max 3 MB. Leave this empty to keep the current logo.
                        </small>
                        <span wire:loading wire:target="logo">Uploading…</span>
                        @if ($logo && $logo->isPreviewable())
                            <img class="supplier-logo-preview" src="{{ $logo->temporaryUrl() }}" alt="New logo preview">
                        @endif
                        @error('logo') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Short description</span>
                        <input type="text" wire:model="tagline" maxlength="160" placeholder="One line visitors see in the directory.">
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            Up to 160 characters. Shown on category cards and at the top of your page.
                        </small>
                        @error('tagline') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>About the business *</span>
                        <textarea wire:model="description" rows="5" required minlength="20" maxlength="2000"></textarea>
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            Between 20 and 2000 characters. Shown on your public page.
                        </small>
                        @error('description') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Save listing</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </form>
            </div>
        </section>
    </main>
</div>
