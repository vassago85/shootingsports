<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap" style="max-width:820px">
                <p class="label">Account · Notifications</p>
                <h1>Email preferences</h1>
                <p class="lede">
                    Choose which emails you'd like to receive. Account &amp; operational messages (password resets, receipts,
                    MD application updates, enquiry replies) always go out. Everything else is opt-out.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px" role="status" aria-live="polite">
                        {{ session('status') }}
                    </div>
                @endif

                <form wire:submit.prevent="save" class="prefs-form">
                    <div class="prefs-row prefs-master">
                        <label>
                            <input type="checkbox" wire:model.live="marketingMaster">
                            <div>
                                <b>Receive marketing emails</b>
                                <p>Master switch. Turn this off and you'll only get transactional email from us.</p>
                            </div>
                        </label>
                    </div>

                    <fieldset @class(['prefs-cats', 'is-locked' => ! $marketingMaster])>
                        <legend class="sr-only">Categories</legend>

                        <div class="prefs-row">
                            <label>
                                <input type="checkbox" wire:model="matchAlerts" @disabled(! $marketingMaster)>
                                <div>
                                    <b>{{ \App\Enums\EmailCategory::MatchAlerts->label() }}</b>
                                    <p>{{ \App\Enums\EmailCategory::MatchAlerts->description() }}</p>
                                </div>
                            </label>
                        </div>

                        <div class="prefs-row">
                            <label>
                                <input type="checkbox" wire:model="weeklyDigest" @disabled(! $marketingMaster)>
                                <div>
                                    <b>{{ \App\Enums\EmailCategory::WeeklyDigest->label() }}</b>
                                    <p>{{ \App\Enums\EmailCategory::WeeklyDigest->description() }}</p>
                                </div>
                            </label>
                        </div>

                        <div class="prefs-row">
                            <label>
                                <input type="checkbox" wire:model="productUpdates" @disabled(! $marketingMaster)>
                                <div>
                                    <b>{{ \App\Enums\EmailCategory::ProductUpdates->label() }}</b>
                                    <p>{{ \App\Enums\EmailCategory::ProductUpdates->description() }}</p>
                                </div>
                            </label>
                        </div>

                        <div class="prefs-row">
                            <label>
                                <input type="checkbox" wire:model="trialNudges" @disabled(! $marketingMaster)>
                                <div>
                                    <b>{{ \App\Enums\EmailCategory::TrialNudges->label() }}</b>
                                    <p>{{ \App\Enums\EmailCategory::TrialNudges->description() }}</p>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    <div class="prefs-row prefs-locked" aria-hidden="false">
                        <div class="prefs-lock">
                            <div>
                                <b>{{ \App\Enums\EmailCategory::Transactional->label() }}</b>
                                <p>{{ \App\Enums\EmailCategory::Transactional->description() }}</p>
                            </div>
                            <span class="pill">Always on</span>
                        </div>
                    </div>

                    <div class="prefs-actions">
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save preferences</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
