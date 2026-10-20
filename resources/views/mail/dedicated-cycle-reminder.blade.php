<x-mail::message>
# {{ $progress['remaining'] }} more {{ $progress['remaining'] === 1 ? 'activity' : 'activities' }} to log before {{ $progress['window_end']->format('j F') }}

Hi {{ $user->name }},

Your **{{ $progress['body_label'] }}** dedicated-status cycle closes on
**{{ $progress['window_end']->format('l, j F Y') }}**
— that's **{{ $progress['days_to_renewal'] }}
{{ \Illuminate\Support\Str::plural('day', $progress['days_to_renewal']) }}** from now.

So far this cycle you have **{{ $progress['logged'] }} of
{{ $progress['required'] }}** activity-counting matches logged.
That leaves **{{ $progress['remaining'] }}** still to log.

<x-mail::button :url="route('my-log')">
Open my log
</x-mail::button>

When you're ready to submit, print the activity record from your log
page — it's formatted as a supporting document for your renewal.

@include('mail.partials.footer-marketing', ['category' => 'dedicated-status reminder'])
</x-mail::message>
