<x-mail::message>
# Enquiry forwarded to you

**Type:** {{ $enquiry->type->getLabel() }}

**From:** {{ $enquiry->name }} ({{ $enquiry->email }})

@if ($enquiry->phone)
**Phone:** {{ $enquiry->phone }}
@endif
@if ($enquiry->subject)
**Subject:** {{ $enquiry->subject }}
@endif
@if ($enquiry->about)
**About:** {{ $enquiry->about->title ?? $enquiry->about->name }}
@endif

<x-mail::panel>
{!! nl2br(e($enquiry->body)) !!}
</x-mail::panel>

This enquiry was sent through Shooting Sports. Reply to this email and your answer comes back to Shooting Sports, not straight to {{ $enquiry->name }}. Their address is not shown on the public page.
</x-mail::message>
