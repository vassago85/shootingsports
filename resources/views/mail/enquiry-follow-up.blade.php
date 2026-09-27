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

<x-mail::button :url="url('/admin')">
Open admin
</x-mail::button>
</x-mail::message>
