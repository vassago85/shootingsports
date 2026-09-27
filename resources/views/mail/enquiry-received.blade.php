<x-mail::message>
# New enquiry

**Type:** {{ $enquiry->type->getLabel() }}

**From:** {{ $enquiry->name }} ({{ $enquiry->email }})

@if ($enquiry->phone)
**Phone:** {{ $enquiry->phone }}
@endif
@if ($enquiry->subject)
**Subject:** {{ $enquiry->subject }}
@endif
@if ($enquiry->about)
**About:** {{ class_basename($enquiry->about) }} — {{ $enquiry->about->name }}
@endif

<x-mail::panel>
{!! nl2br(e($enquiry->body)) !!}
</x-mail::panel>

<x-mail::button :url="url('/admin')">
Open admin
</x-mail::button>
</x-mail::message>
