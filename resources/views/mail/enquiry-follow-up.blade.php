<x-mail::message>
# {{ $enquiry->name }} replied

**Type:** {{ $enquiry->type->getLabel() }}

**From:** {{ $enquiry->name }} ({{ $enquiry->email }})

@if ($enquiry->subject)
**Subject:** {{ $enquiry->subject }}
@endif

<x-mail::panel>
{!! nl2br(e($reply->body)) !!}
</x-mail::panel>

This reply was sent through Shooting Sports. Reply to this email and your answer comes back to Shooting Sports.

@if ($forStaff)
<x-mail::button :url="url('/admin')">
Open admin
</x-mail::button>
@endif
</x-mail::message>
