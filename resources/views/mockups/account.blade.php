<x-mockups.layout title="My Shooting" active="account">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Account</p>
            <h1>My Shooting</h1>
            <p class="mk-lede">Matches from the sports, clubs and provinces you choose to follow. This mockup does not save an account.</p>
        </header>

        <section class="mk-section">
            <h2>Coming up</h2>
            @if (! $hasFollows)
                <p>Follow a sport, club or province and this list fills from those choices only.</p>
                <div class="mk-actions">
                    <a class="btn" href="{{ $mk('mockups.onboarding') }}">Choose what to follow</a>
                    @if ($previewSport)
                        <a class="btn ghost" href="{{ $mk('mockups.account', ['sport' => [$previewSport]]) }}">Preview {{ $sportOptions->firstWhere('slug', $previewSport)['name'] ?? 'a sport' }}</a>
                    @endif
                </div>
            @else
                @forelse ($coming as $match)
                    <x-mockups.match-row :match="$match" />
                @empty
                    <p>Nothing upcoming matches those follows.</p>
                @endforelse
            @endif
        </section>

        <section class="mk-section">
            <h2>Following</h2>
            @if (! $hasFollows)
                <p>You are not following anything in this preview.</p>
            @else
                <div class="mk-meta">
                    @foreach ($follows['sports'] as $slug)
                        <span>{{ $sportOptions->firstWhere('slug', $slug)['name'] ?? $slug }}</span>
                    @endforeach
                    @foreach ($follows['clubs'] as $slug)
                        <span>{{ $clubOptions->firstWhere('slug', $slug)['name'] ?? $slug }}</span>
                    @endforeach
                    @if ($follows['province'] !== '')
                        <span>{{ str($follows['province'])->replace('_', ' ')->title() }}</span>
                    @endif
                </div>
            @endif
            <div class="mk-actions">
                <a class="btn ghost" href="{{ $mk('mockups.account.following', array_filter(['sport' => $follows['sports']->all(), 'club' => $follows['clubs']->all(), 'province' => $follows['province']], fn ($v) => $v !== '' && $v !== [])) }}">Manage follows</a>
            </div>
        </section>

        <section class="mk-section">
            <h2>Recommended matches</h2>
            <p class="mk-support">Same list as Coming up. Recommendations use only the follows you set, not browsing history.</p>
        </section>

        <section class="mk-section">
            <h2>Recent activity</h2>
            <ul class="mk-activity">
                @forelse ($activity as $item)
                    <li><time>{{ $item['when'] }}</time><a href="{{ $item['href'] }}">{{ $item['text'] }}</a></li>
                @empty
                    <li><span>No recent register changes.</span></li>
                @endforelse
            </ul>
        </section>

        <section class="mk-section">
            <h2>Saved and calendar</h2>
            <p>Nothing is saved in this mockup. On the live site a shooter already has a personal calendar and an attendance log.</p>
            <div class="mk-actions">
                <a class="btn ghost" href="{{ route('my-calendar') }}">My calendar</a>
                <a class="btn ghost" href="{{ route('my-log') }}">Attendance log</a>
                <a class="btn ghost" href="{{ route('settings.notifications') }}">Notifications</a>
            </div>
        </section>
    </div>
</x-mockups.layout>
