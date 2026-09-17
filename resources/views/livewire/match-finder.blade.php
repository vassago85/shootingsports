{{--
    Match Finder — homepage hero search. Progressive disclosure:
    Discipline / Province / When live in the default row alongside the
    Search button. Advanced filters (near town, radius, geo, from/to date)
    live under "More filters" but stay in the DOM so bookmarked URLs,
    tests and the Livewire property surface all keep working.

    Every field still binds to the same Livewire properties; the redirect
    payload in `MatchFinder::search()` is unchanged.
--}}
<div class="console" x-data="{ moreOpen: false, ...matchFinderGeo() }">
    <form wire:submit="search">
        <div class="console-head">
            Match finder <span>{{ $indexed }} disciplines indexed</span>
        </div>

        <div class="console-primary">
            <label class="field">
                <span>Discipline</span>
                <select wire:model="discipline">
                    <option value="">All disciplines</option>
                    @foreach ($disciplines as $item)
                        <option value="{{ $item->slug }}">{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Province</span>
                <select wire:model="province">
                    <option value="">All provinces</option>
                    @foreach ($provinces as $item)
                        <option value="{{ $item->urlSlug() }}">{{ $item->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>When</span>
                <select
                    x-data
                    x-on:change="
                        const v = $event.target.value;
                        if (v === 'weekend') {
                            const now = new Date();
                            const day = now.getDay();
                            const daysUntilSat = (6 - day + 7) % 7;
                            const sat = new Date(now); sat.setDate(now.getDate() + daysUntilSat);
                            const sun = new Date(sat); sun.setDate(sat.getDate() + 1);
                            @this.set('from', sat.toISOString().slice(0,10));
                            @this.set('to', sun.toISOString().slice(0,10));
                        } else if (v === 'month') {
                            const now = new Date();
                            const end = new Date(now); end.setMonth(end.getMonth() + 1);
                            @this.set('from', now.toISOString().slice(0,10));
                            @this.set('to', end.toISOString().slice(0,10));
                        } else if (v === '') {
                            @this.set('from', ''); @this.set('to', '');
                        }
                    "
                >
                    <option value="">Any upcoming</option>
                    <option value="weekend">This weekend</option>
                    <option value="month">Next 30 days</option>
                    <option value="custom" x-on:click="moreOpen = true">Custom range…</option>
                </select>
            </label>
            <button class="btn console-search" type="submit">Search</button>
        </div>

        <div class="console-secondary">
            <button
                type="button"
                class="btn ghost console-more"
                @click="moreOpen = ! moreOpen"
                :aria-expanded="moreOpen ? 'true' : 'false'"
                aria-controls="match-finder-more"
            >
                <span x-text="moreOpen ? 'Hide more filters' : 'More filters'"></span>
            </button>
        </div>

        <div id="match-finder-more" class="console-more-panel" x-show="moreOpen" x-cloak>
            <div class="console-grid">
                <label class="field">
                    <span>Near town</span>
                    <input type="text" wire:model="near" placeholder="e.g. Centurion" autocomplete="address-level2">
                </label>
                <label class="field">
                    <span>Within</span>
                    <select wire:model="radius">
                        <option value="">Any distance</option>
                        @foreach ($radii as $km)
                            <option value="{{ $km }}">{{ $km }} km</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="console-geo">
                <button type="button" class="btn ghost" style="width:auto;padding:8px 14px;margin:0" @click="locate" :disabled="locating">
                    <span x-text="locating ? 'Locating…' : (located ? 'Using my location' : 'Use my location')"></span>
                </button>
                <span class="console-geo-note" x-show="error" x-text="error" style="color:#c45c4a;font-size:12px;margin-left:10px"></span>
                <input type="hidden" wire:model="lat">
                <input type="hidden" wire:model="lng">
            </div>
            <div class="console-grid">
                <label class="field">
                    <span>From</span>
                    <input type="date" wire:model="from">
                </label>
                <label class="field">
                    <span>To</span>
                    <input type="date" wire:model="to">
                </label>
            </div>
        </div>
    </form>
</div>

<script>
    function matchFinderGeo() {
        return {
            locating: false,
            located: false,
            error: '',
            locate() {
                this.error = '';
                if (! navigator.geolocation) {
                    this.error = 'Location not available in this browser.';
                    return;
                }
                this.locating = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.locating = false;
                        this.located = true;
                        @this.set('lat', String(pos.coords.latitude));
                        @this.set('lng', String(pos.coords.longitude));
                        @this.set('near', '');
                        if (! @this.get('radius')) {
                            @this.set('radius', '150');
                        }
                    },
                    () => {
                        this.locating = false;
                        this.error = 'Could not get your location — try a town name instead.';
                    },
                    { enableHighAccuracy: false, timeout: 10000 }
                );
            }
        };
    }
</script>
