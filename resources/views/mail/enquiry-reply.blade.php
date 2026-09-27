<x-mail::message>
# Reply from Shooting Sports

@if ($enquiry->subject)
About **{{ $enquiry->subject }}**.
@endif

<x-mail::panel>
{!! nl2br(e($reply->body)) !!}
</x-mail::panel>

<x-mail::button :url="route('enquiries.thread', $enquiry->reply_token)">
Read the thread
</x-mail::button>

You can answer on that page. We will email you when there is another reply.

@include('mail.partials.footer-transactional')
</x-mail::message>
