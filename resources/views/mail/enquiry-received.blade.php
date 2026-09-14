New enquiry on Shooting Sports

Type: {{ $enquiry->type->getLabel() }}
From: {{ $enquiry->name }} <{{ $enquiry->email }}>
@if ($enquiry->phone)
Phone: {{ $enquiry->phone }}
@endif
@if ($enquiry->subject)
Subject: {{ $enquiry->subject }}
@endif
@if ($enquiry->about)
About: {{ class_basename($enquiry->about) }} — {{ $enquiry->about->name }}
@endif

---
{{ $enquiry->body }}
---

Open /admin to manage enquiries.
