<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My business</p>
                <h1>Update {{ $provider->name }}</h1>
                <p>Add a logo and a short line so your page reads clearly in the Industry directory. Category and town changes still go through staff.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                <form wire:submit="save" class="enquiry-form" novalidate>
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
