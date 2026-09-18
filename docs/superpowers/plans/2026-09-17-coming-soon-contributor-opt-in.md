# Coming-soon Contributor Opt-in Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a double-opt-in contributor interest form on the coming-soon page (role + name + email + note), with Turnstile, Mailgun confirm mail, and Filament Enquiries visibility.

**Architecture:** Extend `Enquiry` with `prelaunch_contributor` type and `pending_confirmation` status; dedicated `POST /coming-soon/interest` + `GET /coming-soon/confirm/{token}`; confirm queues existing staff `EnquiryReceivedMail`.

**Tech Stack:** Laravel 12, Pest, Filament Enquiries, Mailgun (existing), Cloudflare Turnstile (Http siteverify), Blade + existing `.cs-*` CSS.

## Global Constraints

- Reuse Enquiries inbox — no separate Filament resource
- Staff email only after confirm
- Turnstile fail-closed; bypass only when both keys empty in `local`/`testing`
- Gate allowlist only interest POST + confirm GET (not full `/enquiries`)
- Token TTL 48 hours from `confirmation_sent_at`

## File map

| File | Responsibility |
|------|----------------|
| `app/Enums/PrelaunchContributorRole.php` | Role select values + labels |
| `app/Enums/EnquiryType.php` | Add `PrelaunchContributor` |
| `app/Enums/EnquiryStatus.php` | Add `PendingConfirmation` |
| Migration on `enquiries` | Token + sent/confirmed timestamps |
| `app/Support/Turnstile.php` | Siteverify + env bypass |
| `app/Http/Controllers/ComingSoonInterestController.php` | store + confirm |
| `app/Http/Requests/StoreComingSoonInterestRequest.php` | Validation + honeypot/time/Turnstile |
| `app/Mail/PrelaunchContributorConfirmMail.php` | Submitter confirm link |
| `resources/views/mail/prelaunch-contributor-confirm.blade.php` | Text mail body |
| `resources/views/public/coming-soon-confirm.blade.php` | Success / failure pages |
| `resources/views/public/coming-soon.blade.php` | Form + success flash |
| `config/coming-soon.php` | Allowlist |
| `config/services.php` + `.env.example` | Turnstile keys |
| `app/Models/Enquiry.php` | Fillable + confirm helpers |
| `app/Filament/Resources/Enquiries/EnquiryResource.php` | Role column |
| `tests/Feature/ComingSoonInterestTest.php` | Feature coverage |

### Task 1: Enums + migration + model

- [ ] Add `PrelaunchContributorRole`, enum cases, confirmation columns, model methods `issueConfirmationToken()`, `confirmFromToken()`, `confirmationIsExpired()`
- [ ] Migrate
- [ ] Commit

### Task 2: Turnstile helper + config

- [ ] `Turnstile::verify(?string $token, ?string $ip): bool`
- [ ] Env keys in services + example
- [ ] Commit

### Task 3: Store/confirm + mail + routes

- [ ] Form request, controller, mailable, routes, allowlist
- [ ] Duplicate pending → resend; same email+role in new/read → error
- [ ] Commit

### Task 4: Coming-soon UI + CSS

- [ ] Form below facts; success replaces form via session flash
- [ ] Confirm result views
- [ ] Commit

### Task 5: Filament role column

- [ ] Toggleable role badge from `context.role`
- [ ] Commit

### Task 6: Feature tests

- [ ] Gate, submit, confirm, Turnstile fail, expiry, staff mail timing, dashboard count
- [ ] Run `php artisan test --compact tests/Feature/ComingSoonInterestTest.php tests/Feature/ComingSoonGateTest.php`
- [ ] Pint dirty
- [ ] Commit
