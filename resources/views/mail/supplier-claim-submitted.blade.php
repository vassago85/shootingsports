<x-mail::message>
# Supplier claim to review

**Business:** {{ $provider->name }}

**From:** {{ $claim->user?->name }} ({{ $claim->user?->email }})

<x-mail::panel>
{!! nl2br(e($claim->evidence)) !!}
</x-mail::panel>

<x-mail::button :url="url('/admin/claims')">
Review claims
</x-mail::button>
</x-mail::message>
