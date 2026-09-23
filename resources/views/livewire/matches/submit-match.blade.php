<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Submit a match</p>
                <h1>List a match</h1>
                <p>Send the match in now. It stays off the public calendar until we approve it, usually within one working day.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if ($signup_note !== '')
                    <p style="color:var(--slate);margin-bottom:18px">From your signup: {{ $signup_note }}</p>
                @endif

                <form wire:submit="submit" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Match title</span>
                        <input type="text" wire:model="title" required autofocus maxlength="160" placeholder="e.g. Wednesday IPSC">
                        @error('title') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Date</span>
                        <input type="date" wire:model="starts_on" required>
                        @error('starts_on') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Club or series</span>
                        <input type="text" wire:model="host_name" required maxlength="160" placeholder="e.g. Pretoria Rifle &amp; Pistol Club">
                        @error('host_name') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <div class="field">
                        <label for="match-province"><span>Province</span></label>
                        <select id="match-province" wire:model.live="province" wire:key="match-province" required>
                            <option value="">Pick one</option>
                            @foreach ($provinceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('province') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <label class="field">
                        <span>Town (optional)</span>
                        <input type="text" wire:model="town" maxlength="120">
                        @error('town') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Sport (optional)</span>
                        <select wire:model="discipline_id">
                            <option value="">Pick one</option>
                            @foreach ($disciplineOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('discipline_id') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="field">
                        <span>Details (optional)</span>
                        <textarea wire:model="description" rows="4" maxlength="2000" placeholder="Squadding, what to bring, who can enter."></textarea>
                        @error('description') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="btn">Submit match</button>
                </form>
            </div>
        </section>
    </main>
</div>
