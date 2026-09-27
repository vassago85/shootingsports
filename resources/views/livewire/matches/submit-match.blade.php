<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Submit an event</p>
                <h1>List an event</h1>
                <p>Send the event in now. It stays off the public calendar until we approve it, usually within one working day.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if ($signup_note !== '')
                    <p style="color:var(--slate);margin-bottom:18px">From your signup: {{ $signup_note }}</p>
                @endif

                <form wire:submit="submit" class="enquiry-form" novalidate>
                    {{-- Step 1: the event itself. What, when. --}}
                    <div class="reg-step">
                        <p class="reg-step-label"><span class="reg-step-num">1</span> The event</p>
                        <label class="field">
                            <span>Event title</span>
                            <input type="text" wire:model="title" required autofocus maxlength="160" placeholder="e.g. Wednesday IPSC">
                            @error('title') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            <span>Type</span>
                            <select wire:model="kind" required>
                                <option value="competition">Competition</option>
                                <option value="training">Training</option>
                            </select>
                            @error('kind') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            <span>Date</span>
                            <input type="date" wire:model="starts_on" required>
                            @error('starts_on') <span class="err">{{ $message }}</span> @enderror
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
                    </div>

                    {{-- Step 2: the host. New clubs are created as
                         Pending listings so staff see them in the
                         review queue with the match. Copy calls this
                         out so a director does not think they are
                         "adding" a club to the public directory. --}}
                    <div class="reg-step">
                        <p class="reg-step-label"><span class="reg-step-num">2</span> Who is hosting</p>
                        <label class="field">
                            <span>Club, range or series</span>
                            <input type="text" wire:model="host_name" required maxlength="160" placeholder="e.g. Pretoria Rifle &amp; Pistol Club">
                            <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                                If we do not have this host on the register yet, we will create a pending listing under your account. It stays hidden until we approve it with the match.
                            </small>
                            @error('host_name') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    {{-- Step 3: where and any extra context. The
                         venue is captured via province + town so
                         staff can pin the range in the desk after
                         approval — the pending organisation carries
                         the province forward. --}}
                    <div class="reg-step">
                        <p class="reg-step-label"><span class="reg-step-num">3</span> Where &amp; details</p>
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
                            <span>Details (optional)</span>
                            <textarea wire:model="description" rows="4" maxlength="2000" placeholder="Squadding, what to bring, who can enter."></textarea>
                            @error('description') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <button type="submit" class="btn">Submit match</button>
                </form>
            </div>
        </section>
    </main>
</div>
