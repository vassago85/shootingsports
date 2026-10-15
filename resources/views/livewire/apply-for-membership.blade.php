<div class="apply-form" style="margin:14px 0 0; padding:14px; border:1px solid var(--line); border-radius:8px">
    @if (! $organisation->acceptsMembershipApplications())
        {{-- Should never render — the parent view checks the toggle before including the component --}}
    @elseif ($user === null)
        <p><a class="btn" href="{{ route('login') }}?redirect={{ urlencode(url()->current()) }}">Log in to apply for membership</a></p>
    @elseif ($submitted || ($existing && in_array($existing->status->value, ['applied', 'active', 'suspended'])))
        <p class="label">Application status</p>
        @php
            $status = $submitted ? \App\Enums\ClubMemberStatus::Applied : $existing->status;
        @endphp
        @if ($status === \App\Enums\ClubMemberStatus::Applied)
            <p><b>Thanks — your application is with the club.</b> They will be in touch once it has been reviewed.</p>
        @elseif ($status === \App\Enums\ClubMemberStatus::Active)
            <p><b>You are already a member of {{ $organisation->name }}.</b>
            @if ($existing?->member_number)
                Your member number is {{ $existing->member_number }}.
            @endif
            </p>
        @else
            <p>Your membership is currently <b>{{ $status->getLabel() }}</b>. Contact the club if you believe this is wrong.</p>
        @endif
    @else
        <p class="label">Apply to join</p>
        <form wire:submit="apply" style="display:grid; gap:10px">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                <label>
                    <span>Phone</span>
                    <input type="tel" wire:model="phone" maxlength="40" required>
                    @error('phone') <small style="color:#f0776b">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Date of birth (optional)</span>
                    <input type="date" wire:model="dateOfBirth">
                    @error('dateOfBirth') <small style="color:#f0776b">{{ $message }}</small> @enderror
                </label>
            </div>
            <label>
                <span>Category</span>
                <select wire:model="category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                <label>
                    <span>Emergency contact name</span>
                    <input type="text" wire:model="emergencyContactName" maxlength="120" required>
                    @error('emergencyContactName') <small style="color:#f0776b">{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Emergency contact phone</span>
                    <input type="tel" wire:model="emergencyContactPhone" maxlength="40" required>
                    @error('emergencyContactPhone') <small style="color:#f0776b">{{ $message }}</small> @enderror
                </label>
            </div>
            <label style="display:flex; gap:8px; align-items:flex-start">
                <input type="checkbox" wire:model="consent" required>
                <span>I agree to share these details with {{ $organisation->name }} under the Protection of Personal Information Act (POPIA).</span>
            </label>
            @error('consent') <small style="color:#f0776b">{{ $message }}</small> @enderror

            <div>
                <button class="btn" type="submit">Send application</button>
            </div>
        </form>
    @endif
</div>
