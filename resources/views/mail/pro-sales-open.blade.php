<x-mail::message>
# Pro is open

Hi {{ $recipientName }},

You asked us to tell you when Pro opened. You can upgrade from your account.

{{ config('plans.pricing.annual.display') }} or {{ config('plans.pricing.monthly.display') }}. Cancel any time.

<x-mail::button :url="url('/upgrade')">
Upgrade to Pro
</x-mail::button>

@if ($user)
@include('mail.partials.footer-marketing', ['category' => 'product updates', 'user' => $user])
@endif
</x-mail::message>
