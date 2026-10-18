<x-filament-panels::page>
    @php
        $statements = $this->statements();
    @endphp

    <div class="space-y-6">
        @if ($statements->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400"
                 data-test="fee-statements-empty">
                No fee statements yet. Statements are issued monthly once your club has collected payments through Paystack.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <table class="w-full text-sm" data-test="fee-statements-table">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Club</th>
                            <th class="py-2 pr-4 font-medium">Period</th>
                            <th class="py-2 pr-4 text-right font-medium">Total fee</th>
                            <th class="py-2 pr-4 text-right font-medium">VAT</th>
                            <th class="py-2 pr-4 text-right font-medium">Gross</th>
                            <th class="py-2 pr-4 font-medium">Issued at</th>
                            <th class="py-2 font-medium"><span class="sr-only">Download</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statements as $statement)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $statement->organisation?->name }}</td>
                                <td class="py-2 pr-4">{{ $statement->period_start->format('M Y') }}</td>
                                <td class="py-2 pr-4 text-right font-mono">R{{ number_format($statement->total_fee_cents / 100, 2) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">R{{ number_format($statement->total_vat_cents / 100, 2) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">R{{ number_format($statement->total_gross_cents / 100, 2) }}</td>
                                <td class="py-2 pr-4">{{ $statement->issued_at?->format('j M Y') ?? 'Not yet issued' }}</td>
                                <td class="py-2 text-right">
                                    <x-filament::button tag="a" size="xs" color="gray"
                                        :href="route('desk.fee-statements.download', $statement)">
                                        Download CSV
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
