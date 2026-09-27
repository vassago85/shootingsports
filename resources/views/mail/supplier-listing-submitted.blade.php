<x-mail::message>
# New supplier listing

**Business:** {{ $provider->name }}

**Category:** {{ $provider->category?->getLabel() }}

**Place:** {{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }}

**Submitted by:** {{ $provider->claimedBy?->name }} ({{ $provider->claimedBy?->email }})

<x-mail::button :url="url('/admin/providers')">
Review in admin
</x-mail::button>
</x-mail::message>
