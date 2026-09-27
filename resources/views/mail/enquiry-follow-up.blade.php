{{ $enquiry->name }} replied on the site.

Type: {{ $enquiry->type->getLabel() }}
From: {{ $enquiry->name }} <{{ $enquiry->email }}>
@if ($enquiry->subject)
Subject: {{ $enquiry->subject }}
@endif

---
{{ $reply->body }}
---

Open /admin to manage enquiries.
