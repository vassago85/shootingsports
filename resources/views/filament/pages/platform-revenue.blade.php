<x-filament-panels::page>
    @php($tiles = $this->tiles())

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <h3 class="text-sm text-gray-500">MRR</h3>
            <p class="text-2xl font-bold">R{{ number_format($tiles['mrr_cents'] / 100, 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">ARR</h3>
            <p class="text-2xl font-bold">R{{ number_format($tiles['arr_cents'] / 100, 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">Trial → Paid (30d)</h3>
            <p class="text-2xl font-bold">{{ $tiles['trial_to_paid_pct'] }}%</p>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">Active subscriptions by tier</h3>
            <ul class="text-sm">
                @foreach ($tiles['active_subscriptions_by_tier'] as $tier => $count)
                    <li>{{ $tier }}: {{ $count }}</li>
                @endforeach
            </ul>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">Platform fee revenue (30d)</h3>
            <p class="text-2xl font-bold">R{{ number_format($tiles['fee_revenue_30d_cents'] / 100, 2) }}</p>
            <p class="text-xs text-gray-500">+ R{{ number_format($tiles['fee_revenue_vat_30d_cents'] / 100, 2) }} VAT</p>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">Outstanding payables</h3>
            <p class="text-2xl font-bold">R{{ number_format($tiles['outstanding_payables_cents'] / 100, 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <h3 class="text-sm text-gray-500">Failed charges (30d)</h3>
            <p class="text-2xl font-bold">{{ $tiles['failed_charges_30d'] }}</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
