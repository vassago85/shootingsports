Hi {{ $enquiry->name }},

Thanks for offering to help load the South African shooting sport register.

You told us you are interested as: {{ \App\Enums\PrelaunchContributorRole::tryFrom(data_get($enquiry->context, 'role'))?->getLabel() ?? 'contributor' }}.

Please confirm your email by opening this link (valid for 48 hours):

{{ route('coming-soon.confirm', $token) }}

If you did not submit this, you can ignore this message.

— ShootingSports
