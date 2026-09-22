<x-mockups.layout title="Set up My Shooting" active="account">
    <div class="wrap" style="padding-bottom:48px">
        <header class="mk-pagehead">
            <p class="label">Account</p>
            <h1>What should we follow?</h1>
            <p class="mk-lede">Three steps. Skip any of them. Nothing is stored until a real account exists.</p>
        </header>
        <form method="get" action="{{ $mk('mockups.account') }}" id="onboard">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            <div class="mk-steps" aria-hidden="true">
                <span data-pip="1" class="on">1 Sports</span>
                <span data-pip="2">2 Where</span>
                <span data-pip="3">3 Follow</span>
            </div>
            <section class="mk-panel" data-step="1">
                <h2>What do you shoot?</h2>
                <div class="mk-chips">
                    @foreach ($sports as $sport)
                        <label><input type="checkbox" name="sport[]" value="{{ $sport['slug'] }}"> {{ $sport['name'] }}</label>
                    @endforeach
                </div>
                <div class="mk-actions">
                    <button class="btn" type="button" data-goto="2">Continue</button>
                    <a href="{{ $mk('mockups.account') }}">Skip</a>
                </div>
            </section>
            <section class="mk-panel" data-step="2" hidden>
                <h2>Where?</h2>
                <label class="field" style="max-width:320px">
                    <span>Province</span>
                    <select name="province">
                        <option value="">Anywhere in South Africa</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province->value }}">{{ $province->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="mk-support">A radius needs a location. Use Near me on Find a match when you want distance.</p>
                <div class="mk-actions">
                    <button class="btn ghost" type="button" data-goto="1">Back</button>
                    <button class="btn" type="button" data-goto="3">Continue</button>
                    <a href="{{ $mk('mockups.account') }}">Skip</a>
                </div>
            </section>
            <section class="mk-panel" data-step="3" hidden>
                <h2>What do you want to follow?</h2>
                <p class="mk-support">Clubs, in addition to the sports you already ticked.</p>
                <div class="mk-chips">
                    @foreach ($clubs as $club)
                        <label><input type="checkbox" name="club[]" value="{{ $club['slug'] }}"> {{ $club['name'] }}</label>
                    @endforeach
                </div>
                <div class="mk-actions">
                    <button class="btn ghost" type="button" data-goto="2">Back</button>
                    <button class="btn" type="submit">Build my shooting feed</button>
                    <a href="{{ $mk('mockups.account') }}">Skip everything</a>
                </div>
            </section>
        </form>
    </div>
    <script>
        const form = document.getElementById('onboard');
        const show = (step) => {
            form.querySelectorAll('[data-step]').forEach((panel) => {
                panel.hidden = panel.dataset.step !== String(step);
            });
            form.querySelectorAll('[data-pip]').forEach((pip) => {
                pip.classList.toggle('on', pip.dataset.pip === String(step));
            });
        };
        form.addEventListener('click', (event) => {
            const button = event.target.closest('[data-goto]');
            if (!button) return;
            show(button.dataset.goto);
        });
    </script>
</x-mockups.layout>
