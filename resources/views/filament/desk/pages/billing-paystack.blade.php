<x-filament-panels::page>
    @php
        $clubs = $this->managedClubs();
        $organisation = $this->selectedOrganisation();
    @endphp

    <div class="space-y-6">
        @if ($clubs->count() > 1)
            <div class="max-w-sm">
                <label for="billing-paystack-organisation" class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Club</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="billing-paystack-organisation" wire:model.live="organisationId">
                        @foreach ($clubs as $club)
                            <option value="{{ $club->id }}">{{ $club->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endif

        @if ($organisation)
            <livewire:platform.enable-paystack-subaccount :organisation="$organisation" :key="'paystack-'.$organisation->id" />
        @else
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                You do not manage any clubs yet.
            </div>
        @endif
    </div>
</x-filament-panels::page>
