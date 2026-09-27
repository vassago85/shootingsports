Forwarded by Shooting Sports

Type: {{ $enquiry->type->getLabel() }}
From: {{ $enquiry->name }} <{{ $enquiry->email }}>
@if ($enquiry->phone)
Phone: {{ $enquiry->phone }}
@endif
@if ($enquiry->subject)
Subject: {{ $enquiry->subject }}
@endif
@if ($enquiry->about)
About: {{ $enquiry->about->title ?? $enquiry->about->name }}
@endif

---
{{ $enquiry->body }}
---

This message was sent through Shooting Sports. Reply to this email to answer {{ $enquiry->name }} directly. Their address is not shown on the public event page.
