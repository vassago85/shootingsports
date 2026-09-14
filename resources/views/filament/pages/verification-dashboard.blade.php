<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($this->getCounts() as $label => $states)
            <x-filament::section :heading="$label">
                <dl class="space-y-2">
                    @foreach ($states as $state => $count)
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-500">{{ str($state)->headline() }}</dt>
                            <dd class="font-medium">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-gray-500">
        Open Organisations, Venues or Providers and use <strong>Mark verified</strong> on selected rows.
        Listings are never auto-deleted.
    </p>
</x-filament-panels::page>
