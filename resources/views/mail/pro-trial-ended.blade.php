Hi {{ $user->name }},

Your Shooting Sports Pro trial has ended and your account is back on Free.

What's still yours:
  - Every follow, saved search and attendance-log entry you created during the trial is still there. Nothing was deleted.

What changes on Free:
  - You can keep up to 3 club/discipline/venue follows and 1 saved search. Any extras stay visible but are read-only until you upgrade.
  - Your attendance log holds 3 slots. Delete an old one to add a new one.
  - Season export (CSV + printable annual record) is a Pro feature. Everything you logged is still visible on the site.

Want Pro back? One click, no card until you're happy:

  {{ url('/upgrade') }}

Thanks for trying it out.

@include('mail.partials.footer-marketing', ['category' => 'trial reminder'])
