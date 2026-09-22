<x-mockups.layout title="{{ data_get($club, 'name', 'Club') }}" active="clubs" :sample="data_get($club, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $club)
            <div class="mk-empty"><strong>No clubs in the register yet.</strong></div>
        @else
            <header class="mk-pagehead">
                <p class="label">{{ $club['type'] }} · {{ $club['place'] ?: 'Location not listed' }}</p>
                <h1>{{ $club['name'] }}</h1>
                <p class="mk-lede">{{ $club['disciplines'] !== [] ? implode(' · ', $club['disciplines']) : 'Sports not listed yet.' }}</p>
                @php
                    $following = $follows['clubs']->contains($club['slug']);
                    $nextClubs = $following
                        ? $follows['clubs']->reject(fn ($s) => $s === $club['slug'])->values()
                        : $follows['clubs']->push($club['slug'])->unique()->values();
                    $followQuery = array_filter([
                        'sport' => $follows['sports']->all(),
                        'club' => $nextClubs->all(),
                        'province' => $follows['province'],
                    ], fn ($value) => $value !== '' && $value !== []);
                @endphp
                <div class="mk-actions">
                    <a class="btn @if ($following) mk-follow is-on @endif" href="{{ $mk('mockups.club', array_merge(['slug' => $club['slug']], $followQuery)) }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? '✓ Following' : 'Follow club' }}</a>
                    @if ($club['website'])
                        <a class="btn ghost" href="{{ $club['website'] }}">Website</a>
                    @endif
                    @if ($club['email'])
                        <a class="btn ghost" href="mailto:{{ $club['email'] }}">Contact</a>
                    @elseif ($club['phone'])
                        <a class="btn ghost" href="tel:{{ $club['phone'] }}">Contact</a>
                    @endif
                </div>
            </header>

            <section class="mk-section">
                <h2>About</h2>
                <div class="mk-prose">
                    @if ($club['description'])
                        <p>{{ $club['description'] }}</p>
                    @else
                        <p>No description has been published.</p>
                    @endif
                </div>
            </section>

            <section class="mk-section">
                <h2>Disciplines</h2>
                @if ($club['disciplines'] === [])
                    <p>No sports are linked yet.</p>
                @else
                    <div class="mk-meta">
                        @foreach ($club['discipline_slugs'] as $index => $slug)
                            <a href="{{ $mk('mockups.sport', ['slug' => $slug]) }}">{{ $club['disciplines'][$index] }}</a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mk-section">
                <h2>Upcoming matches</h2>
                @forelse ($club['upcoming'] as $match)
                    <x-mockups.match-row :match="$match" />
                @empty
                    <p>No upcoming matches are listed.</p>
                @endforelse
            </section>

            <section class="mk-section">
                <h2>Where we shoot</h2>
                @if ($club['range_slug'])
                    <p><a href="{{ $mk('mockups.range', ['slug' => $club['range_slug']]) }}">{{ $club['range'] }}</a></p>
                @else
                    <p>No range is linked from an upcoming match yet.</p>
                @endif
            </section>

            <section class="mk-section">
                <h2>Membership</h2>
                <dl class="mk-dl">
                    <dt>Visitors welcome</dt><dd>{{ $club['visitors_welcome'] ? 'Yes' : 'Not indicated' }}</dd>
                    <dt>Accepting members</dt><dd>Not recorded</dd>
                    <dt>New shooters welcome</dt><dd>Not recorded separately from visitors</dd>
                    <dt>Contact</dt><dd>{{ $club['email'] ?: ($club['phone'] ?: 'Not listed') }}</dd>
                </dl>
            </section>

            <section class="mk-section">
                <h2>Contact</h2>
                <dl class="mk-dl">
                    <dt>Email</dt><dd>{{ $club['email'] ?: 'Not listed' }}</dd>
                    <dt>Phone</dt><dd>{{ $club['phone'] ?: 'Not listed' }}</dd>
                    <dt>Website</dt><dd>@if($club['website'])<a href="{{ $club['website'] }}">{{ $club['website'] }}</a>@else Not listed @endif</dd>
                </dl>
            </section>

            <section class="mk-section">
                <h2>Social links</h2>
                @if ($club['facebook'])
                    <p><a href="{{ $club['facebook'] }}">Facebook</a></p>
                @else
                    <p>No social links are listed.</p>
                @endif
            </section>

            <section class="mk-section">
                <h2>Recent activity</h2>
                <p class="mk-sub">Listing updated {{ $club['updated'] ?: 'on an unknown date' }}. Verification: {{ $club['verification'] ?: 'Not recorded' }}.</p>
            </section>
        @endif
    </div>
</x-mockups.layout>
