<x-mockups.layout title="Club desk" active="account">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Club desk</p>
            <h1>{{ $club['name'] ?? 'No club' }}</h1>
            <p class="mk-lede">Read-only preview of the club-manager desk. Nothing here writes to the register.</p>
        </header>

        @include('mockups.partials.manage-nav', ['active' => 'overview', 'club' => $club])

        @if ($club === null)
            <p>No clubs available in this preview.</p>
        @else
            <div class="strip" style="margin:12px 0 24px">
                <div class="strip-in">
                    <div><b>{{ $matchCount }}</b><span>Upcoming matches</span></div>
                    <div><b>{{ count($club['disciplines']) }}</b><span>Sports listed</span></div>
                    <div><b>{{ $profile['percent'] }}%</b><span>Profile complete</span></div>
                    <div><b>{{ $club['province'] ?: '—' }}</b><span>Home province</span></div>
                    <div><b>{{ $club['verified'] ? 'Verified' : ($club['verification'] ?: 'Unclaimed') }}</b><span>Status</span></div>
                </div>
            </div>

            <div class="mk-actions">
                <a class="btn" href="{{ $mk('mockups.manage.matches.new', ['club' => $club['slug']]) }}">Create match</a>
                <a class="btn ghost" href="{{ $mk('mockups.manage.matches', ['club' => $club['slug']]) }}">Matches</a>
                <a class="btn ghost" href="{{ $mk('mockups.manage.profile', ['club' => $club['slug']]) }}">Club profile</a>
                <a class="btn ghost" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">Public club page</a>
            </div>

            <section class="mk-section">
                <h2>Next matches</h2>
                @forelse ($matches as $match)
                    <x-mockups.match-row :match="$match" />
                @empty
                    <p>No upcoming matches for this club yet. <a class="mk-textlink" href="{{ $mk('mockups.manage.matches.new', ['club' => $club['slug']]) }}">Create the first one</a>.</p>
                @endforelse
                @if ($matchCount > count($matches))
                    <p style="margin-top:12px"><a class="mk-textlink" href="{{ $mk('mockups.manage.matches', ['club' => $club['slug']]) }}">See all {{ $matchCount }}</a></p>
                @endif
            </section>

            <section class="mk-section">
                <h2>Profile completeness</h2>
                <p class="mk-lede">{{ $profile['percent'] }}% complete. Missing fields show on the public club page as gaps.</p>
                <ul style="list-style:none;padding:0;margin:12px 0;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 24px">
                    @foreach ($profile['checks'] as $check)
                        <li style="font-family:var(--f-mono);font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:{{ $check['ok'] ? 'var(--brass-lt)' : 'var(--slate)' }}">
                            {{ $check['ok'] ? '✓' : '·' }} {{ $check['label'] }}
                        </li>
                    @endforeach
                </ul>
                <div class="mk-actions">
                    <a class="btn ghost" href="{{ $mk('mockups.manage.profile', ['club' => $club['slug']]) }}">Edit club profile</a>
                </div>
            </section>

            <section class="mk-section">
                <h2>Recent activity</h2>
                <ul class="mk-activity">
                    @forelse ($activity as $item)
                        <li><time>{{ $item['when'] }}</time><span>{{ $item['text'] }}</span></li>
                    @empty
                        <li><span>No recent activity for this club.</span></li>
                    @endforelse
                </ul>
            </section>

            @if ($clubs->count() > 1)
                <section class="mk-section">
                    <h2>Switch club</h2>
                    <p class="mk-support">You can manage more than one club from one account. The desk shows the club you last opened.</p>
                    <div class="mk-actions">
                        @foreach ($clubs->take(6) as $other)
                            @if ($other['slug'] !== $club['slug'])
                                <a class="btn ghost" href="{{ $mk('mockups.manage.index', ['club' => $other['slug']]) }}">{{ $other['name'] }}</a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-mockups.layout>
