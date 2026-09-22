<x-mockups.admin-layout title="Duplicates" active="duplicates">
    <header class="mk-pagehead">
        <h1>Duplicates</h1>
        <p class="mk-lede">Possible duplicate clubs, ranges and businesses. Nothing is merged from this screen.</p>
    </header>
    <section class="mk-section">
        <h2>Detected in the register</h2>
        @forelse ($duplicates['pairs'] as $pair)
            <article class="mk-row">
                <span>
                    <span class="mk-row-title">{{ $pair['left'] }}</span>
                    <span class="mk-sub">and {{ $pair['right'] }}</span>
                </span>
                <span class="mk-row-meta">{{ $pair['kind'] }} · {{ $pair['reason'] }}</span>
                <span class="mk-actions">
                    <button class="btn ghost" type="button" disabled>Not duplicate</button>
                    <button class="btn ghost" type="button" disabled>Review</button>
                    <button class="btn" type="button" disabled>Merge</button>
                </span>
            </article>
        @empty
            <p>No name pairs scored above 72% similarity.</p>
        @endforelse
        @forelse ($duplicates['coordinates'] as $group)
            <article class="mk-row">
                <span class="mk-row-title">{{ implode(' · ', $group['records']) }}</span>
                <span class="mk-row-meta">{{ $group['reason'] }}</span>
            </article>
        @empty
            <p>No ranges share coordinates to four decimal places.</p>
        @endforelse
    </section>
    <section class="mk-section">
        <h2>Example review</h2>
        <aside class="mk-internal">
            <span>Example only — not a detected duplicate</span>
            <p>These names are not rows in the register. The card shows the actions a reviewer would get.</p>
        </aside>
        <article class="mk-row">
            <span>
                <span class="mk-row-title">Pretoria Precision Rifle Club</span>
                <span class="mk-sub">and Pretoria PRC</span>
            </span>
            <span class="mk-row-meta">Shared town plus an abbreviated name</span>
            <span class="mk-actions">
                <button class="btn ghost" type="button" disabled>Not duplicate</button>
                <button class="btn ghost" type="button" disabled>Review</button>
                <button class="btn" type="button" disabled>Merge</button>
            </span>
        </article>
    </section>
</x-mockups.admin-layout>
