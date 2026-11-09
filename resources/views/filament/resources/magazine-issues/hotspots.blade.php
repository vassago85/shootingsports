<x-filament-panels::page>
    <livewire:magazine-hotspot-editor :issue="$this->getRecord()" :key="'hotspots-'.$this->getRecord()->getKey()" />
</x-filament-panels::page>
