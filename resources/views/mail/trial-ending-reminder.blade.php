<x-mail::message>
# Your {{ $subscription->organisation->name }} trial

Hi,

@if ($daysUntilTrialEnds === 0)
Your **{{ $subscription->tier->label() }}** trial ends **today**.
@elseif ($daysUntilTrialEnds === 1)
Your **{{ $subscription->tier->label() }}** trial ends **tomorrow** ({{ $subscription->trial_ends_at?->format('j M Y') }}).
@else
Your **{{ $subscription->tier->label() }}** trial ends in **{{ $daysUntilTrialEnds }} days** ({{ $subscription->trial_ends_at?->format('j M Y') }}).
@endif

Add a card now to keep your organisation running without interruption.

<x-mail::button :url="$addCardUrl">
Add a card
</x-mail::button>

If no card is on file when the trial ends, your organisation's access becomes read-only until a card is added.

Questions? Reply to this email and we will help.
</x-mail::message>
