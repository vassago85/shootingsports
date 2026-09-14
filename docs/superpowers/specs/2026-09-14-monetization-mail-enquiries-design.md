# Monetization, mail, enquiries & profile — Design

**Date:** 2026-09-14  
**Status:** Built (local)

## Decisions

| Area | Choice |
|------|--------|
| Ads | Staff-configured; invoice off-site |
| Paid listings | Shops (providers) + ranges (venues); clubs/series free |
| Enquiries | Platform inbox only; no public mailto on listings |
| Anti-spam | Honeypot, time-trap, IP rate limit |
| Mailgun | Admin site settings; encrypted secret; overrides `.env` |
| Profile | Name, email, password + home province, travel radius, digest |

## Build order

1. Settings table + MailSettings + Filament Email page  
2. Profile (desk + admin) with extra fields  
3. Enquiry model + public forms + admin inbox + anti-spam  
4. Ad slot catalog + placement creatives + render; venue tier  

## Non-goals (v1)

- Online checkout / PayFast  
- Auto-forward enquiries to clubs  
- Turnstile (can add later)
