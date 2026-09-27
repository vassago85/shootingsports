<x-mail::message>
# New account

**Name:** {{ $registrant->name }}

**Email:** {{ $registrant->email }}

**Asked to be:** {{ implode(', ', $roles) }}

@if (filled($hostHint))
**Club or series:** {{ $hostHint }}
@endif
@if (filled($businessName))
**Business:** {{ $businessName }}
@endif

<x-mail::button :url="url('/admin/users')">
Review in admin
</x-mail::button>
</x-mail::message>
