Someone wants to claim a supplier listing on Shooting Sports

Business: {{ $provider->name }}
From: {{ $claim->user?->name }} <{{ $claim->user?->email }}>

Why they say it is theirs:
{{ $claim->evidence }}

Review claims in the admin: {{ url('/admin/claims') }}
