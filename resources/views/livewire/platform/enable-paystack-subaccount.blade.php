<div class="enable-subaccount" style="display:grid; gap:14px">
    @if (session('status'))
        <div class="empty" style="border-color:var(--brass)">{{ session('status') }}</div>
    @endif

    @if ($organisation->paystack_subaccount_id)
        <p class="label">Paystack subaccount</p>
        <p>
            <b>{{ $organisation->paystack_subaccount_id }}</b>
            ({{ $organisation->paystack_subaccount_status }}) is set up for {{ $organisation->name }}.
        </p>
    @else
        <form wire:submit="submit" style="display:grid; gap:14px">
            <div>
                <p class="label">Review the agreement</p>
                <div style="max-height:260px; overflow-y:auto; padding:12px; border:1px solid var(--line); border-radius:8px">
                    {!! $agreementBody !!}
                </div>
            </div>

            <div>
                <label style="display:flex; gap:8px; align-items:flex-start">
                    <input type="checkbox" wire:model="agreement_accepted">
                    <span>I accept the Collection Agent Agreement (version {{ $agreementVersion }}) on behalf of {{ $organisation->name }}.</span>
                </label>
                @error('agreement_accepted') <small style="color:#f0776b">{{ $message }}</small> @enderror
            </div>

            <label>
                <span>Business name</span>
                <input type="text" wire:model="business_name" maxlength="120" required>
                @error('business_name') <small style="color:#f0776b">{{ $message }}</small> @enderror
            </label>

            <label>
                <span>Settlement bank (Paystack bank code)</span>
                <input type="text" wire:model="settlement_bank" maxlength="40" required>
                @error('settlement_bank') <small style="color:#f0776b">{{ $message }}</small> @enderror
            </label>

            <label>
                <span>Account number</span>
                <input type="text" wire:model="account_number" inputmode="numeric" maxlength="30" required>
                @error('account_number') <small style="color:#f0776b">{{ $message }}</small> @enderror
            </label>

            <div>
                <button type="submit" class="btn" wire:loading.attr="disabled">Create subaccount</button>
            </div>
        </form>
    @endif
</div>
