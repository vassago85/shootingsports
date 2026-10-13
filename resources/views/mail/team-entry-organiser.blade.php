<x-mail::message>
# New team entry: {{ $entry->team_name }}

**Match:** {{ $entry->event->title }} ({{ \App\Support\EventDate::fact($entry->event->starts_at, $entry->event->ends_at) }})

**Captain:** {{ $entry->captain_name }}

**Email:** {{ $entry->captain_email }}

**Phone:** {{ $entry->captain_phone }}

<x-mail::table>
| # | Name | ID number | Contact number |
|:--|:-----|:----------|:---------------|
@foreach ($entry->members as $index => $member)
| {{ $index + 1 }} | {{ $member['name'] }} | {{ $member['id_number'] }} | {{ $member['phone'] }} |
@endforeach
</x-mail::table>

@if ($entry->proof_of_payment_path)
Proof of payment is attached.
@else
No proof of payment was uploaded. The captain still needs to send it to you.
@endif

This entry was submitted on Shooting Sports. Replies to this email come back to Shooting Sports, not to {{ $entry->captain_name }}, so your email address is not shared. To reach the captain, phone them on the number above.
</x-mail::message>
