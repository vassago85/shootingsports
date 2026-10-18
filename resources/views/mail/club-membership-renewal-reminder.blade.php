<x-mail::message>
# Your {{ $member->organisation->name }} membership

Your membership expires on **{{ $member->expires_at?->format('j M Y') }}** — about **{{ $daysUntilExpiry }} days** from now.

@if ($member->member_number)
Member number: **{{ $member->member_number }}**.
@endif

Renew with your club to keep access to events and the register.

@if (filled($member->organisation->membership_eft_instructions))
<x-mail::panel>
{{ $member->organisation->membership_eft_instructions }}
</x-mail::panel>
@endif

Questions? Reply to this email and we will put you in touch with the club.
</x-mail::message>
