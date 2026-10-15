<x-mail::message>
# {{ $approved ? 'Welcome to '.$member->organisation->name : 'About your '.$member->organisation->name.' application' }}

@if ($approved)
Your membership application has been **approved**.

@if ($member->member_number)
Your member number is **{{ $member->member_number }}**.
@endif
@if ($member->expires_at)
Membership is valid until **{{ $member->expires_at->format('j M Y') }}**.
@endif
@else
Your membership application was **not approved** at this time.

@if (filled($reason))
<x-mail::panel>
{{ $reason }}
</x-mail::panel>
@endif
@endif

Questions? Reply to this email and we will put you in touch with the club.
</x-mail::message>
