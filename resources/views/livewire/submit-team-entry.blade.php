<section class="team-entry" id="team-entry">
    @if ($submitted)
        <h2>Entry sent</h2>
        <p class="match-entry-note">
            We sent your team entry to the organiser and a copy to {{ $captainEmail }}.
            Your place is confirmed by the organiser once they have your payment.
        </p>
        @if (filled($event->entry_payment_instructions))
            <h3>How to pay</h3>
            <div class="prose">{!! nl2br(e($event->entry_payment_instructions)) !!}</div>
        @endif
    @elseif ($open)
        <h2>Enter your team</h2>
        <p class="match-entry-note">
            Teams of {{ $event->team_size }}. Your entry goes straight to the organiser. They confirm entries, not Shooting Sports.
        </p>

        <form wire:submit="submit" class="enquiry-form" novalidate>
            <div class="hp" aria-hidden="true">
                <label for="team_entry_company_website">Company website</label>
                <input type="text" id="team_entry_company_website" wire:model="companyWebsite" tabindex="-1" autocomplete="off">
            </div>

            <label class="field">
                <span>Team name</span>
                <input type="text" wire:model="teamName" required maxlength="120">
                @error('teamName') <span class="err">{{ $message }}</span> @enderror
            </label>

            <div class="team-entry-grid">
                <label class="field">
                    <span>Captain</span>
                    <input type="text" wire:model="captainName" required maxlength="120" autocomplete="name">
                    @error('captainName') <span class="err">{{ $message }}</span> @enderror
                </label>
                <label class="field">
                    <span>Captain email</span>
                    <input type="email" wire:model="captainEmail" required maxlength="255" autocomplete="email">
                    @error('captainEmail') <span class="err">{{ $message }}</span> @enderror
                </label>
                <label class="field">
                    <span>Captain phone</span>
                    <input type="tel" wire:model="captainPhone" required maxlength="40" autocomplete="tel">
                    @error('captainPhone') <span class="err">{{ $message }}</span> @enderror
                </label>
            </div>

            @foreach ($members as $index => $member)
                <fieldset class="team-entry-shooter" wire:key="team-entry-shooter-{{ $index }}">
                    <legend>Shooter {{ $index + 1 }}</legend>
                    <div class="team-entry-grid">
                        <label class="field">
                            <span>Name and surname</span>
                            <input type="text" wire:model="members.{{ $index }}.name" required maxlength="120">
                            @error('members.'.$index.'.name') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>ID number</span>
                            <input type="text" wire:model="members.{{ $index }}.id_number" required maxlength="20" inputmode="numeric" autocomplete="off">
                            @error('members.'.$index.'.id_number') <span class="err">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span>Contact number</span>
                            <input type="tel" wire:model="members.{{ $index }}.phone" required maxlength="40">
                            @error('members.'.$index.'.phone') <span class="err">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </fieldset>
            @endforeach

            <label class="field">
                <span>Proof of payment (optional, PDF or image, max 5 MB)</span>
                <input type="file" wire:model="proofOfPayment" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <span wire:loading wire:target="proofOfPayment" class="match-entry-note">Uploading…</span>
                @error('proofOfPayment') <span class="err">{{ $message }}</span> @enderror
            </label>

            <label class="team-entry-consent">
                <input type="checkbox" wire:model="consent">
                <span>Every shooter on this team agreed that their name, ID number and contact number may be sent to the organiser for this entry.</span>
            </label>
            @error('consent') <p class="err">{{ $message }}</p> @enderror

            <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="submit,proofOfPayment">Send team entry</button>
        </form>
    @endif
</section>
