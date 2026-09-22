<x-mockups.layout title="Club profile" active="account">
    <div class="wrap" style="padding-bottom:120px">
        <header class="mk-pagehead">
            <p class="label">Club desk</p>
            <h1>{{ $club['name'] ?? 'No club' }}</h1>
            <p class="mk-lede">Read-only preview of the club profile editor. Nothing here writes to the register.</p>
        </header>

        @include('mockups.partials.manage-nav', ['active' => 'profile', 'club' => $club])

        @if ($club === null)
            <p>No clubs available in this preview.</p>
        @else
            <form class="mk-form" onsubmit="event.preventDefault(); document.getElementById('save-note').hidden = false;">
                <section>
                    <h2>Identity</h2>
                    <div class="grid">
                        <label class="field"><span>Name</span><input value="{{ $club['name'] }}"></label>
                        <label class="field"><span>Type</span><input value="{{ $club['type'] ?: 'Club' }}" disabled></label>
                        <label class="field"><span>Town</span><input value="{{ $club['town'] }}"></label>
                        <label class="field"><span>Province</span><input value="{{ $club['province'] }}"></label>
                    </div>
                    <label class="field" style="margin-top:12px"><span>Description</span><textarea rows="4">{{ $club['description'] }}</textarea></label>
                </section>

                <section>
                    <h2>Sports</h2>
                    @if ($club['disciplines'] === [])
                        <p class="mk-support">No sports listed. On the live site the club would pick from the same sport list the redesign uses.</p>
                    @else
                        <p>{{ implode(' · ', $club['disciplines']) }}</p>
                    @endif
                </section>

                <section>
                    <h2>Home range</h2>
                    <p>{{ $club['range'] ?: 'Not set — the club has no home range recorded.' }}</p>
                </section>

                <section>
                    <h2>Contact</h2>
                    <div class="grid">
                        <label class="field"><span>Website</span><input value="{{ $club['website'] }}"></label>
                        <label class="field"><span>Email</span><input value="{{ $club['email'] }}"></label>
                        <label class="field"><span>Phone</span><input value="{{ $club['phone'] }}"></label>
                        <label class="field"><span>Facebook</span><input value="{{ $club['facebook'] }}"></label>
                    </div>
                </section>

                <section>
                    <h2>Verification</h2>
                    <p>{{ $club['verified'] ? 'Verified by ShootingSports.' : ($club['verification'] ?: 'Unclaimed — a club committee member can request a claim.') }}</p>
                </section>

                <section>
                    <h2>Completeness</h2>
                    <ul style="list-style:none;padding:0;margin:12px 0;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 24px">
                        @foreach ($profile['checks'] as $check)
                            <li style="font-family:var(--f-mono);font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:{{ $check['ok'] ? 'var(--brass-lt)' : 'var(--slate)' }}">
                                {{ $check['ok'] ? '✓' : '·' }} {{ $check['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </section>

                <div class="mk-savebar">
                    <div class="mk-meter">{{ $profile['percent'] }}% complete <span>Club profile</span></div>
                    <a class="btn ghost" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">Public page</a>
                    <button class="btn" type="submit">Save changes</button>
                    <span id="save-note" hidden>Mockup only. Nothing was written to the register.</span>
                </div>
            </form>
        @endif
    </div>
</x-mockups.layout>
