# Coming-soon contributor opt-in — Design

**Date:** 2026-09-17  
**Status:** Built (local)

## Goal

On the gated coming-soon page, let people who will **add content** (range, match director, club, or business) submit interest. They must **confirm by email** before the lead is treated as real. Staff see pending and confirmed rows in the existing Enquiries inbox. Cloudflare Turnstile blocks bots. Outbound mail uses the existing Mailgun path.

## Decisions

| Area | Choice |
|------|--------|
| Storage / inbox | Extend existing `Enquiry` + Filament Enquiries (Approach 1) |
| Audience | Content contributors only — not general public contact |
| Form fields | Role (single select) + name + email + short note |
| Roles | `range` · `match_director` · `club` · `business` |
| Double opt-in | Required — confirm link before status becomes actionable `new` |
| Pending visibility | Show in dashboard as `pending_confirmation` |
| Staff email | Only after successful email confirmation |
| Submitter email | Confirm-your-email message via Mailgun (queued) |
| Bot protection | Cloudflare Turnstile (server-side verify) + keep honeypot / time-trap / IP rate limit |
| Mailgun | Already wired (`.env` + Filament Mail Settings); no second mail stack |

## Non-goals (v1)

- Creating users, orgs, venues, or providers from the form
- Auto-provisioning MD / club accounts
- General “ask us anything” contact on coming-soon (existing `/contact` stays post-launch)
- SMS confirmation
- Resend-confirmation UI beyond a simple “check your email” success state (optional resend can wait)

## Data model

### New `EnquiryType`

- `prelaunch_contributor` — label: “Pre-launch contributor”

### New `EnquiryStatus`

- `pending_confirmation` — color: gray — label: “Pending email confirmation”
- Existing: `new` → `read` → `closed` unchanged after confirm

### Enquiry row on submit

| Field | Value |
|-------|--------|
| `type` | `prelaunch_contributor` |
| `status` | `pending_confirmation` |
| `name`, `email`, `body` | From form (body = short note) |
| `subject` | Derived, e.g. `Pre-launch: Range` from role label |
| `context` | `{ "role": "range"\|"match_director"\|"club"\|"business" }` |
| `ip_address`, `user_agent` | As today |
| Confirm token | New columns (see below) |

### Migration: confirmation columns on `enquiries`

- `confirmation_token` — nullable string, unique index (random, URL-safe, ~64 chars)
- `confirmation_sent_at` — nullable timestamp
- `confirmed_at` — nullable timestamp
- Token cleared (null) after successful confirm so the link cannot be reused

Token TTL: **48 hours**. Expired / unknown / already-confirmed links show a calm failure page (no stack traces).

## Public flow

```
Coming-soon form
  → Turnstile + honeypot + time-trap + rate limit
  → Create Enquiry (pending_confirmation)
  → Queue PrelaunchContributorConfirmMail to submitter
  → Redirect to inline success / thanks on coming-soon
       (“Check your email to confirm”)

Confirm link GET /coming-soon/confirm/{token}
  → Validate token + not expired + still pending
  → Set status=new, confirmed_at=now, clear token
  → Queue EnquiryReceivedMail to each staff email (existing mailable)
  → Show confirmed success page
```

### Coming-soon gate allowlist

Add:

- `coming-soon/confirm/*` (GET)
- POST target for the form (either same-page Livewire or `enquiries` / dedicated `coming-soon/interest` POST — prefer a **dedicated** route so we do not open full `/enquiries` to the public gate)

Recommended routes (gate-allowlisted):

- `POST /coming-soon/interest` → store
- `GET /coming-soon/confirm/{token}` → confirm

Keep `/contact` and `/enquiries` gated until launch.

### Form UX (coming-soon page)

- Sits below the hero lede / facts — one job: “Help us load the register”
- Single select role (required)
- Name, email, short note (required, short max e.g. 500–1000 chars)
- Turnstile widget
- Honeypot + `form_loaded_at` time trap (same pattern as `StoreEnquiryRequest`)
- Success state replaces the form (no need for a separate marketing thanks page)

## Mail

### Submitter: `PrelaunchContributorConfirmMail` (queued)

- Subject: confirm you want to help with ShootingSports
- Body: role summary + single CTA link to confirm route
- Category: transactional (cannot opt out — no account yet)
- Uses current mailer (Mailgun when configured)

### Staff: existing `EnquiryReceivedMail` (queued)

- Fired **only** after confirm
- Type label distinguishes pre-launch contributor rows

### Mailgun setup (ops, not new code)

- Production: `MAIL_MAILER=mailgun` + domain/secret/endpoint in `.env` **or** Filament **Site Settings → Email**
- Local: keep `MAIL_MAILER=log` (or Mailpit) for tests; feature tests use `Mail::fake()`
- Document in `.env.example` remains; add Turnstile keys alongside

## Turnstile

- Config: `services.turnstile.site_key`, `services.turnstile.secret_key` from env
- `.env.example`: `TURNSTILE_SITE_KEY=`, `TURNSTILE_SECRET_KEY=`
- Server: verify `cf-turnstile-response` via Cloudflare siteverify before creating the enquiry
- Fail closed on missing/invalid token
- Tests: Http::fake the siteverify endpoint; optional bypass only when both keys empty **in `local`/`testing`** so Pest can run without Cloudflare (never bypass in production)

## Filament dashboard

- Enquiries list already shows type + status badges — add `pending_confirmation` and `prelaunch_contributor`
- Column for role from `context.role` (toggleable), same pattern as product/trigger columns
- Default filter optional: hide pending from “actionable” view is **not** required; staff can filter by status
- Staff dashboard “new enquiries” count should **exclude** `pending_confirmation` (only `new`) so the badge stays meaningful

## Error handling

| Case | Behaviour |
|------|-----------|
| Turnstile fail | Validation error on form; no row created |
| Rate limit | Same generic message as contact form |
| Duplicate email while still pending | Resend confirm mail for the existing pending row (update token + `confirmation_sent_at`); do not create a second pending row for same email+type |
| Duplicate after confirmed | Create a new enquiry (they may have another role) **or** reject with “already confirmed — we’ll be in touch”; prefer **allow new row if role differs**, block exact same email+role while status is `new`/`read` |
| Expired token | Friendly page; offer “submit again” link to coming-soon |
| Invalid token | Same friendly page |

## Testing

- Gate on: `POST /coming-soon/interest` allowed; other enquiry routes still redirected
- Valid submit + Turnstile fake → pending row + confirm mail queued; **no** staff mail yet
- Confirm token → status `new`, token cleared, staff mail queued
- Expired / bad token → 200/404 calm page, status unchanged
- Turnstile rejection → 422, no row
- Staff dashboard new-count ignores pending
- Coming-soon page renders form fields + Turnstile site key when configured

## Build order

1. Migration + enums (`EnquiryType`, `EnquiryStatus`) + model fillable/casts  
2. Turnstile config + verification helper  
3. Store + confirm controllers/actions + Form Request  
4. Confirm mailable + views  
5. Coming-soon Blade form + CSS fit to existing `.cs-*`  
6. Allowlist + Filament columns / staff count tweak  
7. Feature tests  
8. Ops: set Mailgun + Turnstile keys on production / local as needed  

## Out of scope notes

Mailgun admin UI already exists (`ManageMailSettings` + `MailSettings`). This work does not rebuild it; it depends on it for delivery of confirm + staff mail.
