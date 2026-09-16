<div class="console" x-data="matchFinderGeo()">
    <form wire:submit="search">
        <div class="console-head">Match finder <span>{{ $indexed }} disciplines indexed</span></div>
        <div class="console-grid">
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
        </div>
        <div class="console-grid" style="margin-top:10px">
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
        <div class="console-geo" style="margin-top:10px">
            <button type="button" class="btn ghost" style="width:auto;padding:8px 14px;margin:0" @click="locate" :disabled="locating">
                <span x-text="locating ? 'Locating…' : (located ? 'Using my location' : 'Use my location')"></span>
            </button>
            <span class="console-geo-note" x-show="error" x-text="error" style="color:#c45c4a;font-size:12px;margin-left:10px"></span>
            <input type="hidden" wire:model="lat">
            <input type="hidden" wire:model="lng">
        </div>
        <div class="console-grid" style="margin-top:10px">
            <label class="field">
                <span>From</span>
                <input type="date" wire:model="from">
            </label>
            <label class="field">
                <span>To</span>
                <input type="date" wire:model="to">
            </label>
        </div>
        <button class="btn" type="submit">Search the calendar</button>
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
