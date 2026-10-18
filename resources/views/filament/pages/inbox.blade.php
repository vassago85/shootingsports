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

    @if ($activeTab === 'all')
        {{-- Merged feed across every queue, oldest-first. Not a Filament
             table (rows come from six different models); each row opens
             the item in its own resource so the per-type actions /
             confirmation modals stay in one place. --}}
        @php $items = $this->getAllItems(); @endphp

        @if ($items->isEmpty())
            <div class="fi-ta-empty-state mt-6 flex flex-col items-center justify-center gap-y-2 p-6 text-center">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedInboxStack" class="h-8 w-8 text-gray-400" />
                <div class="fi-ta-empty-state-heading text-lg font-semibold">Inbox is clear</div>
                <div class="fi-ta-empty-state-description text-sm text-gray-500">Nothing waiting across any queue.</div>
            </div>
        @else
            <div class="fi-ta-ctn mt-4 overflow-x-auto">
                <table class="fi-ta-table w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">What</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">From</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Age</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-2 align-top">
                                    <x-filament::badge :color="$row['color']" size="xs">
                                        {{ $row['label'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">{{ \Illuminate\Support\Str::limit((string) $row['what'], 70) }}</div>
                                    @if (! empty($row['context']))
                                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit((string) $row['context'], 90) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-400">
                                    {{ $row['from'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    {{ $this->ageLabelPublic($row['created_at']) }}
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    @if (! empty($row['open_url']))
                                        <a href="{{ $row['open_url'] }}"
                                           class="fi-link text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                            Open →
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-gray-500">Showing the oldest 50 items across every queue. Jump to a tab for row actions.</p>
            </div>
        @endif
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>
