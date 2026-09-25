New supplier listing on Shooting Sports

Business: {{ $provider->name }}
Category: {{ $provider->category?->getLabel() }}
Place: {{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ') }}
Submitted by: {{ $provider->claimedBy?->name }} <{{ $provider->claimedBy?->email }}>

Review it in the admin: {{ url('/admin/providers') }}
