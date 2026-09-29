@switch($screen)

    {{-- 1. Onboarding --------------------------------------------------- --}}
    @case('onboarding')
        <div class="mk-hero">
            <div class="kicker">Set up your calendar</div>
            <h2>Find shoots that fit your weekend.</h2>
            <p>Choose where you shoot and the sports you want to follow. You can browse without signing in.</p>
            <div class="mk-metric-row">
                <div class="mk-metric"><strong>{{ count($matches) }}</strong><span>matches</span></div>
                <div class="mk-metric"><strong>{{ count($sports) }}</strong><span>sports</span></div>
                <div class="mk-metric"><strong>{{ count($ranges) }}</strong><span>ranges</span></div>
            </div>
        </div>
        <div class="mk-card" style="margin-top: 14px;">
            <strong>Use my location</strong>
            <div class="mk-meta">Show nearby events and calculate distance. If skipped, choose a province manually.</div>
            <div class="mk-field">
                <select disabled>
                    <option>Gauteng</option>
                    <option>Western Cape</option>
                    <option>KwaZulu-Natal</option>
                    <option>Nationwide</option>
                </select>
            </div>
        </div>
        <div class="mk-section-title">
            <h3>Follow sports</h3>
            <a href="{{ $app('sports') }}">Select all</a>
        </div>
        <div class="mk-pref-grid">
            @php $sportList = array_slice($sports, 0, 6); @endphp
            @foreach ($sportList as $i => $sport)
                <div @class(['mk-pref', 'on' => $i < 4])>
                    <span aria-hidden="true">{{ $i < 4 ? '✓' : '+' }}</span>
                    {{ $sport['name'] }}
                </div>
            @endforeach
            @if (empty($sportList))
                <div class="mk-pref"><span>+</span>IPSC</div>
                <div class="mk-pref"><span>+</span>Precision Rifle</div>
                <div class="mk-pref"><span>+</span>Clay Target</div>
                <div class="mk-pref"><span>+</span>PR22 / Rimfire</div>
            @endif
        </div>
        <a class="mk-btn wide" href="{{ $app('home') }}">Continue</a>
        <a class="mk-skip" href="{{ $app('home') }}">Skip for now</a>
        @break

    {{-- 2. Home --------------------------------------------------------- --}}
    @case('home')
        <div class="mk-hero">
            <div class="kicker">This weekend · Nationwide</div>
            <h2>What's shooting this weekend?</h2>
            <p>{{ count($matches) }} upcoming matches on the register. Filter by sport, province or club.</p>
            <div class="mk-hero-actions">
                <a class="mk-btn outline" href="{{ $app('events') }}">View weekend</a>
                <a class="mk-btn secondary" href="{{ $app('find') }}">Near me</a>
            </div>
            <div class="mk-metric-row">
                <div class="mk-metric"><strong>{{ count($matches) }}</strong><span>matches</span></div>
                <div class="mk-metric"><strong>{{ count($sports) }}</strong><span>sports</span></div>
                <div class="mk-metric"><strong>{{ count($ranges) }}</strong><span>ranges</span></div>
            </div>
        </div>
        <div class="mk-section-title">
            <h3>Recommended next</h3>
            <a href="{{ $app('events') }}">See all</a>
        </div>
        <div class="mk-list">
            @forelse (array_slice($matches, 0, 3) as $i => $row)
                <a class="mk-event" href="{{ $app('detail', ['slug' => $row['slug']]) }}">
                    <div class="mk-datebox">
                        <span>{{ $row['month'] }}</span>
                        <strong>{{ $row['day'] }}</strong>
                    </div>
                    <div class="mk-event-main">
                        <div class="mk-event-title">{{ $row['title'] }}</div>
                        <div class="mk-meta">{{ $row['place'] ?: 'South Africa' }}</div>
                        <div class="mk-tag-row">
                            @if ($row['discipline'])<span class="mk-tag">{{ $row['discipline'] }}</span>@endif
                            @if ($row['entry'])<span class="mk-tag">Entries open</span>@endif
                        </div>
                    </div>
                    <button type="button" @class(['mk-save', 'on' => $i === 1]) aria-label="Save event">{{ $i === 1 ? '★' : '☆' }}</button>
                </a>
            @empty
                <div class="mk-card"><strong>No upcoming matches yet.</strong><div class="mk-meta">The register updates as clubs publish their calendars.</div></div>
            @endforelse
        </div>
        <div class="mk-section-title">
            <h3>Following activity</h3>
            <a href="{{ $app('following') }}">Manage</a>
        </div>
        <div class="mk-activity">
            <div class="icon" aria-hidden="true">+</div>
            <div>
                <strong>New match added</strong>
                <div class="mk-meta">Matches your followed sports</div>
            </div>
        </div>
        @break

    {{-- 3. Events ------------------------------------------------------- --}}
    @case('events')
        <div class="mk-filter-strip">
            <a class="on" href="{{ $app('events') }}">This weekend</a>
            <a href="{{ $app('events') }}">Next 30 days</a>
            <a href="{{ $app('events') }}">Gauteng</a>
            <a href="{{ $app('events') }}">IPSC</a>
        </div>
        <div class="mk-filter-card">
            <strong>Filters</strong>
            <div class="mk-filter-line">
                <span class="mk-filter-pill on">This weekend</span>
                <span class="mk-filter-pill on">Nationwide</span>
                <span class="mk-filter-pill">Sport</span>
                <span class="mk-filter-pill">Discipline</span>
                <span class="mk-filter-pill">Club or range</span>
            </div>
            <div class="mk-field">
                <input value="Search events, clubs, towns or ranges" aria-label="Search" readonly>
            </div>
        </div>
        <div class="mk-section-title">
            <h3>{{ count($matches) }} matches</h3>
            <a href="{{ $app('find') }}">Map</a>
        </div>
        <div class="mk-list">
            @forelse ($matches as $i => $row)
                <a class="mk-event" href="{{ $app('detail', ['slug' => $row['slug']]) }}">
                    <div class="mk-datebox">
                        <span>{{ $row['month'] }}</span>
                        <strong>{{ $row['day'] }}</strong>
                    </div>
                    <div class="mk-event-main">
                        <div class="mk-event-title">{{ $row['title'] }}</div>
                        <div class="mk-meta">{{ collect([$row['place'] ?: 'South Africa', $row['host']])->filter()->implode(' · ') }}</div>
                        <div class="mk-tag-row">
                            @if ($row['discipline'])<span class="mk-tag">{{ $row['discipline'] }}</span>@endif
                            @if ($row['entry'])<span class="mk-tag">Entries open</span>@endif
                            @if ($row['fee'])<span class="mk-tag">{{ $row['fee'] }}</span>@endif
                        </div>
                    </div>
                    <button type="button" @class(['mk-save', 'on' => $i === 1 || $i === 4]) aria-label="Save event">{{ ($i === 1 || $i === 4) ? '★' : '☆' }}</button>
                </a>
            @empty
                <div class="mk-card"><strong>No matches match those filters.</strong><div class="mk-meta">Clear a filter or widen the date range.</div></div>
            @endforelse
        </div>
        @break

    {{-- 4. Event detail ------------------------------------------------- --}}
    @case('detail')
        @if ($match)
            <div class="mk-detail-hero">
                <div class="mk-tag-row">
                    @if ($match['discipline'])<span class="mk-tag">{{ $match['discipline'] }}</span>@endif
                    @if ($match['host'])<span class="mk-tag">{{ $match['host'] }}</span>@endif
                </div>
                <h2>{{ $match['title'] }}</h2>
                <p>{{ trim(($match['dow'] ?? '').' '.($match['day'] ?? '').' '.($match['month'] ?? '')) }} · {{ $match['venue'] ?: ($match['place'] ?: 'Venue TBC') }}</p>
            </div>
            <div class="mk-quick-actions">
                <button type="button"><span class="i" aria-hidden="true">★</span><span>Save</span></button>
                <button type="button"><span class="i" aria-hidden="true">↗</span><span>Share</span></button>
                <button type="button"><span class="i" aria-hidden="true">＋</span><span>Calendar</span></button>
                <button type="button"><span class="i" aria-hidden="true">⌖</span><span>Directions</span></button>
            </div>
            <div class="mk-card">
                <strong>Match details</strong>
                <div class="mk-meta">{{ $match['venue'] ?: 'Venue to be announced' }}{{ $match['fee'] ? ' · Entry '.$match['fee'] : '' }}</div>
                <div class="mk-tag-row">
                    @if ($match['entry'])
                        <span class="mk-tag">Entries open</span>
                        <span class="mk-tag">External registration</span>
                    @else
                        <span class="mk-tag">Entry details on the day</span>
                    @endif
                </div>
                @if ($match['entry'])
                    <a class="mk-btn wide" href="{{ $app('detail', ['slug' => $match['slug']]) }}">Open entry link</a>
                @endif
            </div>
            <div class="mk-map">
                <div class="route" aria-hidden="true"></div>
                <div class="pin" aria-hidden="true"></div>
                <span class="mk-map-label" style="left:15px;top:14px">{{ $match['place'] ?: 'South Africa' }}</span>
                <span class="mk-map-label" style="right:12px;bottom:14px">Route</span>
            </div>
            <div class="mk-card" style="margin-top: 12px;">
                <strong>Organiser</strong>
                <div class="mk-meta">{{ $match['host'] ?: 'To be announced' }}</div>
            </div>
        @else
            <div class="mk-card">
                <strong>Event detail</strong>
                <div class="mk-meta">No match to show yet. When the register has upcoming events, they open here.</div>
            </div>
        @endif
        @break

    {{-- 5. Find --------------------------------------------------------- --}}
    @case('find')
        <div class="mk-segmented">
            <a class="on" href="{{ $app('find') }}">All</a>
            <a href="{{ $app('find') }}">Clubs</a>
            <a href="{{ $app('find') }}">Ranges</a>
            <a href="{{ $app('find') }}">Industry</a>
        </div>
        <div class="mk-find-map">
            <span class="mk-map-label" style="left:18px;top:20px">Pretoria</span>
            <span class="mk-map-label" style="right:18px;top:72px">Rayton</span>
            <span class="mk-map-label" style="left:56px;bottom:58px">Alberton</span>
        </div>
        <div class="mk-finder-list">
            @foreach (array_slice($ranges, 0, 2) as $row)
                <a class="mk-finder-row" href="{{ $app('find') }}">
                    <div class="mk-avatar" aria-hidden="true">R</div>
                    <div>
                        <strong>{{ $row['name'] }}</strong>
                        <div class="mk-meta">Range{{ $row['place'] ? ' · '.$row['place'] : '' }}{{ $row['distance'] ? ' · '.$row['distance'] : '' }}</div>
                    </div>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>
            @endforeach
            @foreach (array_slice($clubs, 0, 2) as $row)
                <a class="mk-finder-row" href="{{ $app('find') }}">
                    <div class="mk-avatar" aria-hidden="true">C</div>
                    <div>
                        <strong>{{ $row['name'] }}</strong>
                        <div class="mk-meta">{{ collect([$row['type'], $row['place']])->filter()->implode(' · ') }}</div>
                    </div>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>
            @endforeach
            @foreach (array_slice($suppliers, 0, 2) as $row)
                <a class="mk-finder-row" href="{{ $app('find') }}">
                    <div class="mk-avatar" aria-hidden="true">I</div>
                    <div>
                        <strong>{{ $row['name'] }}</strong>
                        <div class="mk-meta">{{ collect([$row['category'] ?: 'Industry', $row['place']])->filter()->implode(' · ') }}</div>
                    </div>
                    <span class="arrow" aria-hidden="true">›</span>
                </a>
            @endforeach
            @if (empty($ranges) && empty($clubs) && empty($suppliers))
                <div class="mk-finder-row">
                    <div class="mk-avatar" aria-hidden="true">?</div>
                    <div><strong>Nothing published yet</strong><div class="mk-meta">Clubs, ranges and suppliers appear as the register grows.</div></div>
                    <span class="arrow" aria-hidden="true">›</span>
                </div>
            @endif
        </div>
        @break

    {{-- 6. Sports ------------------------------------------------------- --}}
    @case('sports')
        <div class="mk-field">
            <input value="Search sport or discipline" aria-label="Search sports" readonly>
        </div>
        <div class="mk-section-title">
            <h3>Discover disciplines</h3>
            <a href="{{ $app('sports') }}">Quiz</a>
        </div>
        <div class="mk-sport-grid">
            @php $sportList = array_slice($sports, 0, 6); @endphp
            @forelse ($sportList as $i => $sport)
                <a class="mk-sport-tile" href="{{ $app('sports') }}">
                    <div class="mk-sport-icon" aria-hidden="true">{{ $i + 1 }}</div>
                    <strong>{{ $sport['name'] }}</strong>
                    <div class="mk-meta">{{ $sport['family'] ?: 'South African shooting sport' }}</div>
                    <div class="mk-progress"><span style="width: {{ 30 + $i * 8 }}%"></span></div>
                </a>
            @empty
                @foreach (['IPSC', 'Precision Rifle', 'Clay Target', 'PR22 / Rimfire'] as $i => $label)
                    <div class="mk-sport-tile">
                        <div class="mk-sport-icon" aria-hidden="true">{{ $i + 1 }}</div>
                        <strong>{{ $label }}</strong>
                        <div class="mk-meta">South African shooting sport</div>
                        <div class="mk-progress"><span style="width: {{ 30 + $i * 12 }}%"></span></div>
                    </div>
                @endforeach
            @endforelse
        </div>
        @break

    {{-- 7. Following ---------------------------------------------------- --}}
    @case('following')
        <div class="mk-filter-strip">
            <a class="on" href="{{ $app('following') }}">Saved</a>
            <a href="{{ $app('following') }}">Sports</a>
            <a href="{{ $app('following') }}">Clubs</a>
            <a href="{{ $app('following') }}">Provinces</a>
        </div>
        <div class="mk-card">
            <strong>Your weekend</strong>
            <div class="mk-meta">Saved events and followed interests would appear here for signed-in shooters.</div>
        </div>
        <div class="mk-list">
            @foreach (array_slice($matches, 0, 3) as $row)
                <a class="mk-event" href="{{ $app('detail', ['slug' => $row['slug']]) }}">
                    <div class="mk-datebox">
                        <span>{{ $row['month'] }}</span>
                        <strong>{{ $row['day'] }}</strong>
                    </div>
                    <div class="mk-event-main">
                        <div class="mk-event-title">{{ $row['title'] }}</div>
                        <div class="mk-meta">{{ $row['place'] ?: 'South Africa' }}</div>
                        <div class="mk-tag-row">
                            @if ($row['discipline'])<span class="mk-tag">{{ $row['discipline'] }}</span>@endif
                        </div>
                    </div>
                    <button type="button" class="mk-save on" aria-label="Saved">★</button>
                </a>
            @endforeach
        </div>
        <div class="mk-section-title">
            <h3>Following</h3>
            <a href="{{ $app('following') }}">Edit</a>
        </div>
        <div class="mk-tag-row">
            @foreach (array_slice($sports, 0, 4) as $sport)
                <span class="mk-tag">{{ $sport['name'] }}</span>
            @endforeach
            <span class="mk-tag">Gauteng</span>
        </div>
        @break

    {{-- 8. Activity ----------------------------------------------------- --}}
    @case('activity')
        <div class="mk-card">
            <strong>Notification settings</strong>
            <div class="mk-toggle-row">
                <div>
                    <strong>Saved event changes</strong>
                    <div class="mk-meta">Date, venue or cancellation</div>
                </div>
                <div class="mk-switch" aria-hidden="true"><span></span></div>
            </div>
            <div class="mk-toggle-row">
                <div>
                    <strong>Entry reminders</strong>
                    <div class="mk-meta">Opening, closing and day-before</div>
                </div>
                <div class="mk-switch" aria-hidden="true"><span></span></div>
            </div>
            <div class="mk-toggle-row">
                <div>
                    <strong>New followed events</strong>
                    <div class="mk-meta">Sports, clubs and provinces</div>
                </div>
                <div class="mk-switch off" aria-hidden="true"><span></span></div>
            </div>
        </div>
        <div class="mk-list">
            <div class="mk-activity">
                <div class="icon danger" aria-hidden="true">!</div>
                <div>
                    <strong>Match cancelled</strong>
                    <div class="mk-meta">Organiser posted today</div>
                </div>
            </div>
            <div class="mk-activity">
                <div class="icon warn" aria-hidden="true">↺</div>
                <div>
                    <strong>Venue updated</strong>
                    <div class="mk-meta">Saved event · directions refreshed</div>
                </div>
            </div>
            <div class="mk-activity">
                <div class="icon" aria-hidden="true">+</div>
                <div>
                    <strong>New match added</strong>
                    <div class="mk-meta">Matches your followed sports</div>
                </div>
            </div>
            <div class="mk-activity">
                <div class="icon warn" aria-hidden="true">⏱</div>
                <div>
                    <strong>Entry closes tomorrow</strong>
                    <div class="mk-meta">Saved match</div>
                </div>
            </div>
        </div>
        @break

    {{-- 9. Profile ------------------------------------------------------ --}}
    @case('profile')
        <div class="mk-avatar-row">
            <div class="mk-avatar" aria-hidden="true">P</div>
            <div>
                <strong>Paul Charsley</strong>
                <div class="mk-meta">Gauteng · Shooter · Organiser enabled</div>
            </div>
        </div>
        <div class="mk-card">
            <strong>Account state</strong>
            <div class="mk-meta">Browse without signing in. Save, follow, notifications and organiser submissions ask for an account.</div>
            <a class="mk-btn wide" href="{{ $app('profile') }}">Manage sign-in</a>
        </div>
        <div class="mk-card">
            <strong>Preferences</strong>
            <div class="mk-toggle-row">
                <div>
                    <strong>Use location</strong>
                    <div class="mk-meta">Nearby events and distance filters</div>
                </div>
                <div class="mk-switch" aria-hidden="true"><span></span></div>
            </div>
            <div class="mk-toggle-row">
                <div>
                    <strong>Calendar prompts</strong>
                    <div class="mk-meta">Ask after saving an event</div>
                </div>
                <div class="mk-switch" aria-hidden="true"><span></span></div>
            </div>
            <div class="mk-toggle-row">
                <div>
                    <strong>Weekly digest</strong>
                    <div class="mk-meta">Thursday evening summary</div>
                </div>
                <div class="mk-switch" aria-hidden="true"><span></span></div>
            </div>
        </div>
        <a class="mk-btn wide" href="{{ $app('organiser') }}">Open organiser tools</a>
        @break

    {{-- 10. Organiser --------------------------------------------------- --}}
    @case('organiser')
        <div class="mk-card">
            <strong>Submit event</strong>
            <div class="mk-stepper" aria-hidden="true">
                <span class="step on"></span>
                <span class="step on"></span>
                <span class="step"></span>
                <span class="step"></span>
            </div>
            <div class="mk-field">
                <input value="{{ $matches[0]['title'] ?? 'New match' }}" aria-label="Event title" readonly>
            </div>
            <div class="mk-field">
                <select disabled>
                    @foreach (array_slice($sports, 0, 5) as $sport)
                        <option>{{ $sport['name'] }}</option>
                    @endforeach
                    @if (empty($sports))
                        <option>IPSC</option>
                        <option>Precision Rifle</option>
                    @endif
                </select>
            </div>
            <div class="mk-field">
                <input value="{{ isset($matches[0]) ? trim(($matches[0]['dow'] ?? '').' '.($matches[0]['day'] ?? '').' '.($matches[0]['month'] ?? '')) : '10 Oct 2026 · 08:00' }}" aria-label="Date and time" readonly>
            </div>
            <div class="mk-field">
                <input value="{{ $matches[0]['venue'] ?? 'Venue name' }}" aria-label="Venue" readonly>
            </div>
            <div class="mk-field">
                <textarea rows="3" readonly>Match details, round count, division notes. External entry link.</textarea>
            </div>
            <a class="mk-btn wide" href="{{ $app('organiser') }}">Preview and submit</a>
        </div>
        <div class="mk-section-title">
            <h3>Managed events</h3>
            <a href="{{ $app('organiser') }}">All</a>
        </div>
        <div class="mk-list">
            @forelse (array_slice($matches, 0, 2) as $i => $row)
                <a class="mk-event" href="{{ $app('detail', ['slug' => $row['slug']]) }}">
                    <div class="mk-datebox">
                        <span>{{ $row['month'] }}</span>
                        <strong>{{ $row['day'] }}</strong>
                    </div>
                    <div class="mk-event-main">
                        <div class="mk-event-title">{{ $row['title'] }}</div>
                        <div class="mk-meta">{{ $i === 0 ? 'Published · 184 views' : 'Draft · missing entry link' }}</div>
                        <div class="mk-tag-row">
                            @if ($row['discipline'])<span class="mk-tag">{{ $row['discipline'] }}</span>@endif
                            <span @class(['mk-tag', 'warn' => $i !== 0])>{{ $i === 0 ? 'Entries open' : 'Draft' }}</span>
                        </div>
                    </div>
                    <button type="button" class="mk-save" aria-label="Save">☆</button>
                </a>
            @empty
                <div class="mk-card">
                    <strong>No managed events yet.</strong>
                    <div class="mk-meta">Submit an event to see it here.</div>
                </div>
            @endforelse
        </div>
        @break

    @default
        <div class="mk-card">
            <strong>ShootingSports</strong>
            <div class="mk-meta">Pick a screen from the picker above.</div>
        </div>
@endswitch
