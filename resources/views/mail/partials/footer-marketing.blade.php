
—
You're receiving this because you opted in to {{ $category ?? 'marketing' }} emails from Shooting Sports.

Unsubscribe (one click):
{{ \App\Support\EmailPreferences::unsubscribeUrl($user) }}

Fine-tune which emails you get (sign in required):
{{ \App\Support\EmailPreferences::preferencesUrl() }}
