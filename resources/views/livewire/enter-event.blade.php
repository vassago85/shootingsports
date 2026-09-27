<div>
    @if ($open)
        @auth
            @if ($entry && $entry->status === \App\Enums\EntryStatus::Entered)
                <p class="match-entry-note">You are entered.@if ($entry->payment_status === \App\Enums\EntryPaymentStatus::Paid) Fee paid on this site.@elseif ($entry->payment_status === \App\Enums\EntryPaymentStatus::External) Pay the entry fee to the organiser.@endif</p>
                <button type="button" class="btn ghost" wire:click="withdraw">Withdraw</button>
            @elseif ($full)
                <p class="match-entry-note">This event is full.</p>
            @else
                <form wire:submit="enter" class="match-entry-cta">
                    <label class="field">
                        <span>Division (optional)</span>
                        <input type="text" wire:model="division" maxlength="80" placeholder="e.g. Open">
                    </label>
                    @error('division') <p class="err">{{ $message }}</p> @enderror
                    <button type="submit" class="btn">
                        @if ($event->entry_collection === \App\Enums\EntryCollection::Paystack)
                            Pay and enter
                        @else
                            Enter this event
                        @endif
                    </button>
                    <p class="match-entry-note">
                        @if ($event->entry_collection === \App\Enums\EntryCollection::Paystack)
                            The entry fee is charged here. We are not the organiser.
                        @else
                            This records your entry. You still pay the organiser. We do not take the fee.
                        @endif
                    </p>
                </form>
            @endif
        @else
            <a class="btn" href="{{ route('login') }}">Sign in to enter</a>
        @endauth
    @endif
</div>
