<x-filament-panels::page>
    <div class="max-w-2xl space-y-4">
        <p>{{ $this->clubPlanSummary() }}</p>
        <p>Uploading events, the public club page, and a link out to your own entry system stay free.</p>
        @foreach ($this->unpaidDeskClubs() as $club)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold">{{ $club->name }}</p>
                @if (config('club-plans.enabled'))
                    <a class="text-sm underline" href="{{ route('club-plan.show', $club) }}">Subscribe</a>
                @else
                    <a class="text-sm underline" href="{{ route('contact') }}">Ask about the club subscription</a>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
