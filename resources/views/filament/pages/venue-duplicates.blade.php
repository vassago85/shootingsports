<x-filament-panels::page>
    @php($groups = $this->getGroups())

    @if ($groups === [])
        <x-filament::section>
            <p class="text-sm text-gray-500">No likely duplicates right now. Groups appear when venues share a town and a similar name.</p>
        </x-filament::section>
    @else
        <div class="space-y-6">
            @foreach ($groups as $group)
                <x-filament::section :heading="'Group · '.$group['venues']->count().' venues'">
                    <ul class="divide-y divide-gray-700">
                        @foreach ($group['venues'] as $venue)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                <div>
                                    <a
                                        href="{{ \App\Filament\Resources\Venues\VenueResource::getUrl('edit', ['record' => $venue]) }}"
                                        class="font-medium text-primary-400 hover:underline"
                                    >{{ $venue->name }}</a>
                                    <span class="text-gray-500"> · {{ $venue->town }}{{ $venue->province ? ' · '.$venue->province->getLabel() : '' }}</span>
                                    @if ($venue->hasCoordinates())
                                        <span class="text-gray-500"> · pin</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($group['venues'] as $other)
                                        @if ($other->id !== $venue->id)
                                            <x-filament::button
                                                size="sm"
                                                color="warning"
                                                wire:click="mergeInto({{ $venue->id }}, {{ $other->id }})"
                                                wire:confirm="Merge «{{ $other->name }}» into «{{ $venue->name }}»? Events move; the duplicate is archived."
                                            >
                                                Keep this · merge {{ Str::limit($other->name, 24) }}
                                            </x-filament::button>
                                        @endif
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
