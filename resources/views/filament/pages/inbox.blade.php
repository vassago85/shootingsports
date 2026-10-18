<x-filament-panels::page>
    {{--
        Horizontal tab strip. Each button sets $activeTab and swaps the
        table definition on the Inbox page. Uses Filament's own badge
        colour conventions so a hot queue reads at a glance.
    --}}
    <nav
        class="fi-tabs flex flex-wrap items-center gap-1 border-b border-gray-200 pb-2 dark:border-white/10"
        aria-label="{{ __('Inbox tabs') }}"
    >
        @foreach ($this->getTabs() as $tabKey => $tabInfo)
            @php
                $isActive = $activeTab === $tabKey;
                $count = $tabInfo['count'];
            @endphp

            <button
                type="button"
                wire:click="setActiveTab('{{ $tabKey }}')"
                class="fi-tabs-item inline-flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition
                    {{ $isActive
                        ? 'bg-primary-600 text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}"
                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
            >
                <span>{{ $tabInfo['label'] }}</span>

                {{-- Always render the count — zero is useful information
                     ("nothing waiting here") rather than noise, and
                     stops staff clicking through six tabs to find out. --}}
                <span
                    class="fi-badge inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold
                        {{ $isActive
                            ? 'bg-white/20 text-white'
                            : ($count > 0
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-200'
                                : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400') }}"
                >
                    {{ $count }}
                </span>
            </button>
        @endforeach
    </nav>

    {{ $this->table }}
</x-filament-panels::page>
