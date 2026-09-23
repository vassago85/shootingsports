<x-mockups.layout title="Find somewhere to shoot" description="Matches, clubs, ranges and shooting sports across South Africa." active="home">
    <section class="mk-finder">
        <div class="wrap">
            <p class="label">ShootingSports</p>
            <h1>Find somewhere to shoot.</h1>
            <p class="lede">Matches, clubs, ranges and shooting sports across South Africa.</p>
            <form class="mk-finder-form" method="get" action="{{ $mk('mockups.matches') }}">
                <label class="field">
                    <span>What do you shoot?</span>
                    <select name="sport">
                        <option value="">Any sport</option>
                        @foreach ($sportOptions as $sport)
                            <option value="{{ $sport['slug'] }}">{{ $sport['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Where?</span>
                    <select name="province">
                        <option value="">Anywhere</option>
                        <option value="near">Near me</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->value }}">{{ $province->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>When?</span>
                    <input type="date" name="from">
                </label>
                <button class="btn" type="submit">Find matches</button>
            </form>
            <p class="mk-context">{{ $stats['matches'] }} upcoming matches · {{ $stats['ranges'] }} ranges · {{ $stats['sports'] }} sports</p>
        </div>
    </section>

    <section class="mk-section">
        <div class="wrap">
            <div class="mk-headrow">
                <h2>{{ $weekend ? 'This weekend' : 'Coming up' }}</h2>
                <a class="mk-textlink" href="{{ $weekend ? $mk('mockups.matches', ['from' => $weekendFrom, 'to' => $weekendTo]) : $mk('mockups.matches') }}">View all matches</a>
            </div>
            @forelse ($matches as $match)
                <x-mockups.match-row :match="$match" />
            @empty
                <p class="mk-support">No upcoming matches are listed yet.</p>
            @endforelse
        </div>
    </section>

    @if ($sports->isNotEmpty())
        <section class="mk-section">
            <div class="wrap">
                <div class="mk-headrow">
                    <h2>Explore shooting sports</h2>
                    <a class="mk-textlink" href="{{ $mk('mockups.sports') }}">All sports</a>
                </div>
                <div class="mk-sport-grid">
                    @foreach ($sports as $sport)
                        <a class="mk-sport-cell" href="{{ ($sport['children'] ?? []) !== [] ? $mk('mockups.sport', ['slug' => $sport['slug']]) : $mk('mockups.matches', ['sport' => $sport['slug']]) }}">
                            <strong>{{ $sport['name'] }}</strong>
                            @if ($sport['upcoming_count'])
                                <span>{{ $sport['upcoming_count'] }} upcoming</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="mk-section">
        <div class="wrap">
            <h2>Find somewhere to shoot</h2>
            <div class="mk-split">
                <a class="mk-choice" href="{{ $mk('mockups.clubs') }}">
                    <span class="label">Clubs</span>
                    <strong>Find people shooting the same sports.</strong>
                </a>
                <a class="mk-choice" href="{{ $mk('mockups.ranges') }}">
                    <span class="label">Ranges</span>
                    <strong>Find somewhere to shoot.</strong>
                </a>
            </div>
        </div>
    </section>

    @if ($activity !== [])
        <section class="mk-section">
            <div class="wrap">
                <h2>Shooting activity</h2>
                <ul class="mk-activity">
                    @foreach (array_slice($activity, 0, 5) as $item)
                        <li><time>{{ $item['when'] }}</time><a href="{{ $item['href'] }}">{{ $item['text'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="mk-section">
        <div class="wrap">
            <h2>New to shooting?</h2>
            <p class="mk-support">Start with a sport, then look for a match marked beginner friendly.</p>
            <p><a class="mk-textlink" href="{{ $mk('mockups.matches', ['beginner' => 1]) }}">Beginner friendly matches</a></p>
        </div>
    </section>

    <section class="mk-section">
        <div class="wrap">
            <h2>Clubs and organisers</h2>
            <p class="mk-support">List a match on the national calendar. It is free to publish.</p>
            <p><a class="btn" href="{{ $mk('mockups.manage.matches.new') }}">List an event</a></p>
            <p class="mk-support" style="margin-top:18px"><a class="mk-textlink" href="{{ $mk('mockups.industry') }}">Shooting industry</a></p>
        </div>
    </section>
</x-mockups.layout>
