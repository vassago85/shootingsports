<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Submit an event</p>
                <h1>List an event</h1>
                <p>Four quick steps — date, match, venue, review — then we take it from there. Your submission stays off
                the public calendar until we approve it, usually within one working day.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:640px">
                @if ($signup_note !== '')
                    <p style="color:var(--slate);margin-bottom:14px">From your signup: {{ $signup_note }}</p>
                @endif

                {{-- Step indicator. Earlier steps are click-to-return
                     so a shooter who notices they got the date wrong
                     on the Review step can jump back without hitting
                     Back three times. --}}
                <ol class="wiz-steps" aria-label="Submission steps">
                    @foreach (['Date', 'Match', 'Venue', 'Review'] as $i => $label)
                        @php
                            $n = $i + 1;
                            $state = $step === $n ? 'current' : ($step > $n ? 'done' : 'future');
                        @endphp
                        <li class="wiz-step wiz-step-{{ $state }}">
                            @if ($state === 'done')
                                <button type="button" wire:click="goTo({{ $n }})" class="wiz-step-btn">
                                    <span class="wiz-step-num">{{ $n }}</span>
                                    <span class="wiz-step-label">{{ $label }}</span>
                                </button>
                            @else
                                <span class="wiz-step-btn">
                                    <span class="wiz-step-num">{{ $n }}</span>
                                    <span class="wiz-step-label">{{ $label }}</span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <form wire:submit.prevent="submit" class="wiz-form" novalidate>
                    {{-- Step 1 — Date & time. Each step is its own keyed
                         element so morphing cannot reuse the date input
                         as the title or host field on the next step. --}}
                    @if ($step === 1)
                        <div wire:key="wizard-step-date">
                        <h2 class="wiz-h2">When is the match?</h2>
                        <label class="field">
                            <span>Date</span>
                            <input type="date" wire:key="starts-on" wire:model="starts_on" required>
                            @error('starts_on') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field wiz-inline-check">
                            <input type="checkbox" wire:key="all-day" wire:model.live="all_day">
                            <span>All day</span>
                        </label>

                        @unless ($all_day)
                            <div class="wiz-time-grid" wire:key="wizard-times">
                                <label class="field">
                                    <span>Start time</span>
                                    <input type="time" wire:key="starts-at-time" wire:model="starts_at_time">
                                    @error('starts_at_time') <span class="err">{{ $message }}</span> @enderror
                                </label>
                                <label class="field">
                                    <span>End time</span>
                                    <input type="time" wire:key="ends-at-time" wire:model="ends_at_time">
                                    @error('ends_at_time') <span class="err">{{ $message }}</span> @enderror
                                </label>
                            </div>
                        @endunless
                        </div>

                    {{-- Step 2 — Match -----------------------------------------------}}
                    @elseif ($step === 2)
                        <div wire:key="wizard-step-match">
                        <h2 class="wiz-h2">What is the match?</h2>
                        <label class="field">
                            <span>Match title</span>
                            <input type="text" wire:key="match-title" wire:model="title" required autofocus maxlength="160" placeholder="e.g. Wednesday IPSC">
                            @error('title') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            <span>Type</span>
                            <select wire:key="match-kind" wire:model="kind" required>
                                <option value="competition">Competition</option>
                                <option value="training">Training</option>
                            </select>
                            @error('kind') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            <span>Sport (optional)</span>
                            <select wire:key="match-discipline" wire:model="discipline_id">
                                <option value="">Pick one</option>
                                @foreach ($disciplineOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('discipline_id') <span class="err">{{ $message }}</span> @enderror
                        </label>

                        <label class="field">
                            <span>Details (optional)</span>
                            <textarea wire:key="match-description" wire:model="description" rows="4" maxlength="2000"
                                placeholder="Squadding, what to bring, who can enter."></textarea>
                            @error('description') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        </div>

                    {{-- Step 3 — Venue -----------------------------------------------}}
                    @elseif ($step === 3)
                        <div wire:key="wizard-step-venue">
                        <h2 class="wiz-h2">Who's hosting and where?</h2>
                        <label class="field">
                            <span>Club, range or series</span>
                            <input type="text" wire:key="host-name" wire:model="host_name" required maxlength="160"
                                placeholder="e.g. Pretoria Rifle &amp; Pistol Club">
                            <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                                If we don't have this host on the register yet, we'll create a pending listing under your account.
                                It stays hidden until we approve it with the match.
                            </small>
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
                            <input type="text" wire:key="match-town" wire:model="town" maxlength="120">
                            @error('town') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        </div>

                    {{-- Step 4 — Review ----------------------------------------------}}
                    @else
                        <div wire:key="wizard-step-review">
                        <h2 class="wiz-h2">Does this look right?</h2>
                        <dl class="wiz-review">
                            <div class="wiz-review-row">
                                <dt>Date</dt>
                                <dd>
                                    {{ $starts_on ?: '— Not set' }}
                                    @if ($all_day)
                                        · All day
                                    @elseif ($starts_at_time || $ends_at_time)
                                        · {{ $starts_at_time ?: '—' }} to {{ $ends_at_time ?: '—' }}
                                    @endif
                                </dd>
                                <dd class="wiz-review-edit"><button type="button" wire:click="goTo(1)">Edit</button></dd>
                            </div>

                            <div class="wiz-review-row">
                                <dt>Match</dt>
                                <dd>
                                    <strong>{{ $title ?: '— No title' }}</strong><br>
                                    {{ $kindLabel }} · {{ $disciplineLabel }}
                                    @if ($description)
                                        <br><span class="muted" style="font-size:13px">{{ \Illuminate\Support\Str::limit($description, 160) }}</span>
                                    @endif
                                </dd>
                                <dd class="wiz-review-edit"><button type="button" wire:click="goTo(2)">Edit</button></dd>
                            </div>

                            <div class="wiz-review-row">
                                <dt>Venue</dt>
                                <dd>
                                    <strong>{{ $host_name ?: '— No host' }}</strong><br>
                                    {{ $provinceLabel }}{{ $town ? ' · '.$town : '' }}
                                </dd>
                                <dd class="wiz-review-edit"><button type="button" wire:click="goTo(3)">Edit</button></dd>
                            </div>
                        </dl>

                        <p class="muted" style="font-size:13px;margin:14px 0 0">
                            Submitting saves the match as a draft. Our team gets a notification and usually
                            approves within one working day.
                        </p>
                        </div>
                    @endif

                    {{-- Nav row — Back on steps 2-4, Next on 1-3, Submit on 4. ------}}
                    <div class="wiz-nav">
                        @if ($step > 1)
                            <button type="button" class="btn ghost" wire:click="back">← Back</button>
                        @endif

                        @if ($step < $totalSteps)
                            <button type="button" class="btn" wire:click="next" wire:loading.attr="disabled" wire:target="next">
                                <span wire:loading.remove wire:target="next">Next →</span>
                                <span wire:loading wire:target="next">…</span>
                            </button>
                        @else
                            <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="submit">
                                <span wire:loading.remove wire:target="submit">Submit match</span>
                                <span wire:loading wire:target="submit">Submitting…</span>
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
