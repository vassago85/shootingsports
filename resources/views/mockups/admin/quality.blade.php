<x-mockups.admin-layout title="Needs attention" active="quality">
    <header class="mk-pagehead">
        <h1>Needs attention</h1>
        <p class="mk-lede">Problems found in the current register. Counts are capped in each group so the page stays usable.</p>
    </header>
    <div class="mk-meta" style="margin-bottom:16px">
        @foreach ($quality['summary'] as $item)
            <span class="mk-badge {{ $item['severity'] }}">{{ $item['count'] }} {{ $item['label'] }}</span>
        @endforeach
    </div>
    @foreach ($quality['groups'] as $group)
        <section class="mk-section" id="{{ strtolower($group['title']) }}">
            <h2>{{ $group['title'] }}</h2>
            @forelse ($group['items'] as $item)
                <a class="mk-row" href="{{ $item['href'] }}">
                    <span class="mk-row-title">{{ $item['title'] }}</span>
                    <span class="mk-row-meta">{{ $item['problem'] }}</span>
                    <span class="mk-badge {{ $item['severity'] }}">Fix</span>
                </a>
            @empty
                <p>Nothing in this group.</p>
            @endforelse
        </section>
    @endforeach
</x-mockups.admin-layout>
