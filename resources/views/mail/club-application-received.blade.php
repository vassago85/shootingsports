<x-mail::message>
# New membership application

**{{ $member->fullName() }}** has applied to join **{{ $member->organisation->name }}**.

<x-mail::table>
| Field | Value |
|:------|:------|
| Email | {{ $member->email ?: '—' }} |
| Phone | {{ $member->phone ?: '—' }} |
| Category | {{ $member->category->getLabel() }} |
| Date of birth | {{ $member->date_of_birth?->format('j M Y') ?: '—' }} |
| Emergency contact | {{ $member->emergency_contact_name ?: '—' }}{{ $member->emergency_contact_phone ? ' · '.$member->emergency_contact_phone : '' }} |
</x-mail::table>

Review and approve the application in Desk.

<x-mail::button :url="route('filament.desk.resources.club-members.edit', $member)">
Open in Desk
</x-mail::button>
</x-mail::message>
