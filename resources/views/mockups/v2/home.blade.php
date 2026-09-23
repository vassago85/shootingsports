<x-mockups.v2.layout title="Find your sport" description="The national register of South African shooting sport." active="home">
    <section class="v2-hero-home">
        <div class="v2-wrap">
            <div style="padding: 36px 0">
                <p class="v2-kicker">The national register of South African shooting sport</p>
                <h1>Find your <em>sport</em>.<br>Find your <em>club</em>.<br>Find your <em>match</em>.</h1>
                <p class="v2-lede">Every discipline, every province, one calendar. South African shooting sport in one place.</p>
                <div class="v2-hero-actions">
                    <a class="v2-btn v2-btn-lime" href="{{ $mk('mockups.v2.matches', ['from' => $weekendFrom, 'to' => $weekendTo]) }}">What’s shooting this weekend?</a>
                </div>
            </div>
            <aside class="v2-rail" aria-label="Coming up">
                <header>
                    <h2>{{ $weekend ? 'This weekend' : 'Next 30 days' }}</h2>
                    <span>Nationwide</span>
                </header>
                @forelse ($matches as $match)
                    <a class="item" href="{{ $mk('mockups.v2.match', ['slug' => $match['slug']]) }}">
                        <span class="d">
                            <span>{{ $match['month'] }}</span>
                            <b>{{ $match['day'] }}</b>
                        </span>
                        <span>
                            <span class="t">{{ $match['title'] }}</span>
                            <span class="m">{{ collect([$match['town'] ?? null, $match['province'] ?? null, $match['discipline'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="m" style="padding:8px">No upcoming matches listed yet.</p>
                @endforelse
            </aside>
        </div>
    </section>

    <section class="v2-stats" aria-label="Register">
        <div class="v2-stats-in">
            <div><span>Matches</span><b>{{ $stats['matches'] }}</b></div>
            <div><span>Ranges</span><b>{{ $stats['ranges'] }}</b></div>
            <div><span>Disciplines</span><b>{{ $stats['sports'] }}</b></div>
            <div><span>Clubs</span><b>{{ $stats['clubs'] }}</b></div>
        </div>
    </section>

    <section class="v2-section">
        <div class="v2-wrap">
            <div class="v2-headrow">
                <div>
                    <p class="v2-kicker">Start here</p>
                    <h2>What are you interested in?</h2>
                </div>
                <a href="{{ $mk('mockups.v2.sports') }}">Explore all sports</a>
            </div>
            <div class="v2-divisions">
                @foreach (\App\Enums\Division::publicCases() as $division)
                    <a class="v2-division" href="{{ $mk('mockups.v2.sports', ['division' => $division->value]) }}">
                        <span>Division</span>
                        <strong>{{ $division->getLabel() }}</strong>
                        <em>Open {{ strtolower($division->getLabel()) }}</em>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="v2-section">
        <div class="v2-wrap">
            <div class="v2-headrow">
                <h2>Coming up</h2>
                <a href="{{ $weekend ? $mk('mockups.v2.matches', ['from' => $weekendFrom, 'to' => $weekendTo]) : $mk('mockups.v2.matches') }}">View all matches</a>
            </div>
            <div class="v2-list">
                @forelse ($matches as $match)
                    <x-mockups.v2.match-row :match="$match" />
                @empty
                    <p class="v2-empty">No upcoming matches are listed yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="v2-section">
        <div class="v2-wrap">
            <h2>Find a club or range</h2>
            <div class="v2-choices" style="margin-top:14px">
                <a class="v2-choice" href="{{ $mk('mockups.v2.clubs') }}">
                    <span class="v2-kicker">Clubs and series</span>
                    <strong>Find people shooting the same sports.</strong>
                </a>
                <a class="v2-choice" href="{{ $mk('mockups.v2.ranges') }}">
                    <span class="v2-kicker">Ranges</span>
                    <strong>Find a range near you.</strong>
                </a>
            </div>
        </div>
    </section>

    <section class="v2-section">
        <div class="v2-wrap v2-lower">
            <div class="v2-sidebox soft">
                <h2>New to shooting?</h2>
                <p>Not sure where to start? Learn about the different shooting disciplines and how to get involved.</p>
                <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.sports') }}">Explore shooting sports</a>
            </div>
            @if ($activity !== [])
                <div>
                    <div class="v2-headrow"><h2>Latest activity</h2></div>
                    <ul class="v2-activity">
                        @foreach (array_slice($activity, 0, 4) as $item)
                            @php
                                $path = parse_url($item['href'], PHP_URL_PATH) ?: '';
                                $href = $item['href'];
                                if (preg_match('#/mockups/(match|range)/([^/]+)#', $path, $found) === 1) {
                                    $href = $mk('mockups.v2.'.$found[1], ['slug' => $found[2]]);
                                }
                            @endphp
                            <li><time>{{ $item['when'] }}</time><a href="{{ $href }}">{{ $item['text'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
</x-mockups.v2.layout>
