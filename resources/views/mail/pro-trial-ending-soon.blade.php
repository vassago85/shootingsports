Hi {{ $user->name }},

Quick heads-up: your 30-day Shooting Sports Pro trial ends on {{ $trialEndsAt?->format('l, j F Y') }} — three days from now.

What happens automatically when the trial ends:
  - You go back on Free. No card was ever asked for, so nothing charges.
  - Your unlimited follows, saved searches and attendance-log entries stay visible — none are deleted.
  - New follows, new saved searches and new log entries above the Free cap are blocked until you upgrade.

Want to keep Pro past the trial? Add a card any time between now and {{ $trialEndsAt?->format('j F') }}:

  {{ url('/upgrade') }}

You'll be handed off to Paystack Checkout — your card details never touch our servers. Cancel any time.

Not for you? No action needed. You'll roll to Free automatically and we won't nag.

@include('mail.partials.footer-marketing', ['category' => 'trial reminder'])
