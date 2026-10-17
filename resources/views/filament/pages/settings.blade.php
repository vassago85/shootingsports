<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($this->getSections() as $section)
            <a
                href="{{ $section['href'] }}"
                class="fi-card group flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-5 transition hover:border-primary-500 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500"
            >
                <div class="fi-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-filament::icon :icon="$section['icon']" class="h-5 w-5" />
                </div>

                <div class="flex-1">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $section['title'] }}
                    </h3>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $section['description'] }}
                    </p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
