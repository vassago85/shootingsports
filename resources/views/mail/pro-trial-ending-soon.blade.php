<x-mail::message>
# Your Pro trial ends in 3 days

Hi {{ $user->name }},

Your 30-day Shooting Sports Pro trial ends on {{ $trialEndsAt?->format('l, j F Y') }}.

What happens automatically when the trial ends:

- You go back on Free. No card was ever asked for, so nothing charges.
- Your unlimited follows, saved searches and attendance-log entries stay visible. None are deleted.
- New follows, new saved searches and new log entries above the Free cap are blocked until you upgrade.

<x-mail::button :url="url('/upgrade')">
Keep Pro
</x-mail::button>

You will be handed off to Paystack Checkout. Your card details never touch our servers. Cancel any time.

Not for you? No action needed. You will roll to Free automatically.

@include('mail.partials.footer-marketing', ['category' => 'trial reminder'])
</x-mail::message>
