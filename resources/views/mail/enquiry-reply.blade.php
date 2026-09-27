Shooting Sports replied to your enquiry
@if ($enquiry->subject)
about "{{ $enquiry->subject }}"
@endif

---
{{ $reply->body }}
---

You can read the thread and answer on the site:
{{ route('enquiries.thread', $enquiry->reply_token) }}
