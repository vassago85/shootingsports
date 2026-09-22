<x-mockups.layout title="Find your sport" description="The national register of South African shooting sport." active="home">
    <section class="hero" style="padding:0">
        <div class="hero-reticle-wrap" aria-hidden="true">
            <svg class="hero-reticle" viewBox="0 0 400 400">
                <circle cx="200" cy="200" r="182" fill="none" stroke="#F1F2EE" stroke-width="1.5"/>
                <circle cx="200" cy="200" r="120" fill="none" stroke="#F1F2EE" stroke-width="1"/>
                <circle cx="200" cy="200" r="52" fill="none" stroke="#F1F2EE" stroke-width="1"/>
                <path d="M200 0v150M200 250v150M0 200h150M250 200h150" stroke="#F1F2EE" stroke-width="1.5"/>
                <path d="M182 236h36M186 258h28M190 280h20M182 164h36M186 142h28M190 120h20" stroke="#F1F2EE" stroke-width="1.5"/>
                <path d="M164 182v36M142 186v28M120 190v20M236 182v36M258 186v28M280 190v20" stroke="#F1F2EE" stroke-width="1.5"/>
                <circle class="hero-reticle-dot" cx="200" cy="200" r="3.5" fill="#6B7D3A"/>
            </svg>
        </div>
        <div class="hero-in">
            <div>
                <p class="label">The national register of South African shooting sport</p>
                <h1>Find your <em>sport</em>. Find your <em>club</em>. Find your <em>match</em>.</h1>
                <p class="lede">Every discipline, every province, one calendar. Free to list, free to browse, no account needed to look around.</p>
                <p class="hero-weekend">
                    <a class="btn" href="{{ $mk('mockups.matches.calendar', ['month' => $weekendMonth, 'from' => $weekendFrom, 'to' => $weekendTo]) }}">What's shooting this weekend?</a>
                </p>
                <form class="console" method="get" action="{{ $mk('mockups.matches.calendar') }}" data-finder>
                    <div class="console-primary">
                        <label class="field">
                            <span>Sport</span>
                            <select name="sport">
                                <option value="">Any sport</option>
                                @foreach ($sportOptions as $sport)
                                    <option value="{{ $sport['slug'] }}">{{ $sport['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Province / near me</span>
                            <select name="province">
                                <option value="">Any province</option>
                                <option value="near">Near me</option>
                                @foreach ($provinces as $province)
                                    <option value="{{ $province->value }}">{{ $province->getLabel() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="from">
                        </label>
                        <button class="btn console-search" type="submit">Find matches</button>
                    </div>
                </form>
                <div class="quick-actions" role="group" aria-label="Quick match filters">
                    <a href="{{ $mk('mockups.matches.calendar', ['month' => $weekendMonth, 'from' => $weekendFrom, 'to' => $weekendTo]) }}">This weekend</a>
                    <a href="{{ $mk('mockups.matches.calendar') }}" data-near-link>Near me</a>
                    <a href="{{ $mk('mockups.matches.calendar', ['province' => 'gauteng']) }}">Gauteng</a>
                    <a href="{{ $mk('mockups.matches.calendar', ['beginner' => 1]) }}">New shooter friendly</a>
                </div>
            </div>
            <div class="hero-rail">
                <div class="rail-head">
                    <h3>Coming up</h3>
                    <span class="label">Nationwide</span>
                </div>
                @forelse ($matches as $match)
                    <a class="rail-item" href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">
                        <div class="rail-date">
                            <span class="dow">{{ $match['dow'] }}</span>
                            <b>{{ $match['day'] }}</b>
                            <span class="mo">{{ $match['month'] }}</span>
                        </div>
                        <div class="rail-body">
                            <div class="t">{{ $match['title'] }}</div>
                            <div class="m">{{ collect([$match['town'] ?: $match['province'], $match['discipline']])->filter()->implode(' · ') }}</div>
                        </div>
                    </a>
                @empty
                    <p class="empty">No upcoming matches listed yet.</p>
                @endforelse
                <p class="rail-more">
                    <a href="{{ $mk('mockups.matches.calendar') }}">Open the calendar →</a>
                </p>
            </div>
        </div>
    </section>

    <div class="strip">
        <div class="strip-in">
            <div><span>Matches</span><b>{{ $stats['matches'] }}</b></div>
            <div><span>Ranges</span><b>{{ $stats['ranges'] }}</b></div>
            <div><span>Disciplines</span><b>{{ $stats['sports'] }}</b></div>
            <div><span>Clubs</span><b>{{ $stats['clubs'] }}</b></div>
        </div>
    </div>

    <section class="block" id="divisions" style="padding-top:28px">
        <div class="wrap">
            <div class="sec-head">
                <p class="label">Start here</p>
                <h2>What are you interested in?</h2>
                <p>Four divisions. Open one, choose a discipline, then a sub-discipline if it has one. The match list comes after that. The calendar is the other view of those events.</p>
            </div>
            <div class="division-choices">
                @foreach ($divisions as $division)
                    <a class="division-choice" href="{{ $mk('mockups.sports', ['division' => $division->value]) }}">
                        <span class="nm">{{ $division->getLabel() }}</span>
                        <span class="go">Open {{ strtolower($division->getLabel()) }} →</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-mockups.layout>
