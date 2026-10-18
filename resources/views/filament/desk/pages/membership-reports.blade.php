<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="membership-report-tiles">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Revenue (paid, {{ $this->year }})</div>
                <div class="mt-2 text-2xl font-semibold" data-test="revenue-cents">
                    R{{ number_format($this->revenueCentsForYear() / 100, 2) }}
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Open invoices</div>
                <div class="mt-2 text-2xl font-semibold" data-test="open-invoices">{{ $this->outstandingCount() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Renewals due in {{ $this->year }}</div>
                <div class="mt-2 text-2xl font-semibold" data-test="renewals-due">{{ $this->renewalsDueCount() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Active members</div>
                <div class="mt-2 text-2xl font-semibold" data-test="active-members">
                    {{ $this->statusBreakdown()['active'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="mb-3 text-lg font-semibold">By status</h2>
                <table class="w-full text-sm" data-test="status-breakdown">
                    <tbody>
                        @foreach ($this->statusBreakdown() as $status => $count)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2 capitalize">{{ str_replace('_', ' ', $status) }}</td>
                                <td class="py-2 text-right font-mono">{{ $count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="mb-3 text-lg font-semibold">Active by category</h2>
                <table class="w-full text-sm" data-test="category-breakdown">
                    <tbody>
                        @forelse ($this->categoryBreakdown() as $category => $count)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2 capitalize">{{ $category }}</td>
                                <td class="py-2 text-right font-mono">{{ $count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-2 text-sm text-gray-500">No active members yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-filament::button
                wire:click="downloadRevenueCsv"
                icon="heroicon-o-arrow-down-tray"
                data-test="download-revenue-csv"
            >
                Download revenue CSV
            </x-filament::button>
            <span class="text-sm text-gray-500">Period: 1 Jan–31 Dec {{ $this->year }}</span>
        </div>
    </div>
</x-filament-panels::page>
