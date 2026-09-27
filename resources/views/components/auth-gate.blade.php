@props([
    // Short key for the action — used to lookup the copy string.
    // Known keys: save, follow, log, calendar. Falls back to a
    // generic prompt when unknown.
    'action' => 'save',
    // Button label shown to the guest. Same wording as the
    // authenticated control so nothing shifts under the cursor.
    'label' => 'Sign in',
])

@php
    /*
     * Contextual auth panel for logged-out shooters. Rather than
     * throwing the user onto a login page with no explanation, we
     * render a small ghost button that, when clicked, opens a
     * popover explaining *why* an account is useful for the action
     * they just took. Create / Sign in links preserve the current
     * URL via ?redirect= so they land back where they started.
     *
     * All state is Alpine-local — no Livewire round-trip needed for
     * a modal that just shows two links.
     */
    $copy = [
        'save' => [
            'title' => 'Save events to your Shooting Sports account',
            'body' => 'Follow events, keep your calendar and get reminded before match day.',
        ],
        'follow' => [
            'title' => 'Follow this on Shooting Sports',
            'body' => 'We\'ll surface new matches, results and news for what you follow.',
        ],
        'log' => [
            'title' => 'Log matches you\'ve shot',
            'body' => 'Keep a private log of your matches, scores and notes across every discipline.',
        ],
        'calendar' => [
            'title' => 'Your personal shooting calendar',
            'body' => 'One place for the matches you plan to shoot — with iCal sync and reminders.',
        ],
    ];

    $entry = $copy[$action] ?? [
        'title' => 'Create a free Shooting Sports account',
        'body' => 'Save events, follow clubs and keep your own log of matches you\'ve shot.',
    ];

    $redirect = request()->fullUrl();
    $loginUrl = route('login').'?redirect='.urlencode($redirect);
    $registerUrl = route('register').'?redirect='.urlencode($redirect);
@endphp

<div class="auth-gate" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" class="btn ghost auth-gate-trigger" @click="open = true">
        {{ $label }}
    </button>

    <div
        class="auth-gate-panel"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="dialog"
        aria-modal="true"
        aria-labelledby="auth-gate-title-{{ $action }}"
        @click.outside="open = false"
    >
        <button type="button" class="auth-gate-close" @click="open = false" aria-label="Close">×</button>
        <h3 id="auth-gate-title-{{ $action }}">{{ $entry['title'] }}</h3>
        <p>{{ $entry['body'] }}</p>
        <div class="auth-gate-actions">
            <a class="btn" href="{{ $registerUrl }}">Create free account</a>
            <a class="btn ghost" href="{{ $loginUrl }}">Sign in</a>
        </div>
        <p class="auth-gate-note">Free forever. Browse anything on Shooting Sports without an account.</p>
    </div>
</div>
