<x-filament-panels::page>
    <div class="desk-hero" style="margin-bottom:1.25rem">
        <p class="label">Existing listing</p>
        <h1>Claim ownership</h1>
        <p>Staff must approve before you can edit the listing or add matches under it.</p>
    </div>

    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Submit claim
        </x-filament::button>
    </form>
</x-filament-panels::page>
