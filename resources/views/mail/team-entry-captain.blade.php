<x-mail::message>
# Team entry sent

We sent your entry for **{{ $entry->team_name }}** to the organiser of {{ $entry->event->title }} ({{ \App\Support\EventDate::fact($entry->event->starts_at, $entry->event->ends_at) }}).

<x-mail::table>
| # | Name | Contact number |
|:--|:-----|:---------------|
@foreach ($entry->members as $index => $member)
| {{ $index + 1 }} | {{ $member['name'] }} | {{ $member['phone'] }} |
@endforeach
</x-mail::table>

@if (filled($entry->event->entry_payment_instructions))
## How to pay

<x-mail::panel>
{!! nl2br(e($entry->event->entry_payment_instructions)) !!}
</x-mail::panel>
@endif

@if ($entry->proof_of_payment_path)
Your proof of payment went with the entry.
@else
You did not upload proof of payment. Your place is only confirmed once the organiser has it, so send it to them as soon as you have paid.
@endif

The organiser confirms entries, not Shooting Sports. For questions about the match, use Ask the organiser on Shooting Sports and we forward your message.

<x-mail::button :url="route('enquiries.listing', ['type' => 'event', 'id' => $entry->event->id])">
Ask the organiser
</x-mail::button>
</x-mail::message>
