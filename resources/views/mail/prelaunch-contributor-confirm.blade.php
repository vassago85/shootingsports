<x-mail::message>
# Confirm your email

Hi {{ $enquiry->name }},

Thanks for offering to help load the South African shooting sport register.

You told us you are interested as **{{ \App\Enums\PrelaunchContributorRole::tryFrom(data_get($enquiry->context, 'role'))?->getLabel() ?? 'contributor' }}**.

<x-mail::button :url="route('coming-soon.confirm', $token)">
Confirm your email
</x-mail::button>

This link is valid for 48 hours. If you did not submit this, you can ignore this message.

@include('mail.partials.footer-transactional')
</x-mail::message>
