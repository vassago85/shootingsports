New account on Shooting Sports

Name: {{ $registrant->name }}
Email: {{ $registrant->email }}
Asked to be: {{ implode(', ', $roles) }}
@if (filled($hostHint))
Club or series: {{ $hostHint }}
@endif
@if (filled($businessName))
Business: {{ $businessName }}
@endif

Review them in the admin: {{ url('/admin/users') }}
