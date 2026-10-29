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

@if ($copyEnquirer ?? false)
This enquiry was sent through Shooting Sports. {{ $enquiry->name }} is copied on this email so they can see what was forwarded. Reply to this email and your answer comes back to Shooting Sports, not straight to {{ $enquiry->name }}.
@else
This enquiry was sent through Shooting Sports. Reply to this email and your answer comes back to Shooting Sports, not straight to {{ $enquiry->name }}. Their address is not shown on the public page.
@endif
</x-mail::message>
