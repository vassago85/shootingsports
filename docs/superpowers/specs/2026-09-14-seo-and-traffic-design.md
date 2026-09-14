# SEO + Shared Umami — Design

**Date:** 2026-09-14  
**Status:** Approved for spec (user: Umami + Search Console, TuneUp-plus SEO)

## Goal

Give shootingsports.co.za a privacy-respecting traffic dashboard and a Search Console–ready public head, matching the Tune Up Precision pattern without inventing per-page SEO fields.

## Decisions

| Decision | Choice |
|---|---|
| On-site traffic | Shared Umami at `https://analytics.charsley.co.za` |
| Google | Search Console via the same HTML file used on TuneUp / NRAPA / SAPRF, plus optional meta tag |
| SEO UI | Blade `x-seo-meta` on the public layout — not a Filament editor |
| AI crawlers | `/llms.txt` curated map of public pages |
| Out | GA4, cookie banners, embed-iframe tracking, hreflang, image/news sitemaps |

## What already exists

- Split sitemaps (`/sitemap.xml` + events / organisations / venues / disciplines / providers)
- Per-page `title`, `description`, `canonical`, `robots`
- JSON-LD for Event, SportsOrganization, SportsActivityLocation, LocalBusiness
- `noindex` on filtered directory pages and the embed iframe
- Privacy page is a stub

Gaps: no Open Graph / Twitter cards, no default share image, no site-wide WebSite schema, no Google verification file, `robots.txt` does not list the sitemap and does not hide `/desk` or `/admin`, static pages (home, calendar, claim, advertise, privacy) are missing from the sitemap.

## Traffic — shared Umami

A standalone stack at `/opt/umami` on the Ubuntu box, **not** inside the Shooting Sports compose file. One Postgres + one Umami container. Nginx Proxy Manager points `analytics.charsley.co.za` at Umami’s port.

Versioned recipe lives in this repo at `ops/umami/docker-compose.yml` so it can be copied to `/opt/umami`. Rebuilding Shooting Sports must never recreate Umami.

After first login, create a website in Umami for Shooting Sports and put the IDs in the app `.env`:

```
UMAMI_SCRIPT_URL=https://analytics.charsley.co.za/script.js
UMAMI_WEBSITE_ID=<uuid>
```

The public layout loads the script only when both are set. Desk, admin, and the embed iframe (`/embed/calendar`) do **not** load it — an iframe would double-count when a club site already has its own tracker.

No first-party pageview table. Staff open Umami in a browser. The privacy page states: first-party Umami, no advertising cookies, no sale of data.

This agent cannot SSH. Server commands go in the implementation plan for the user to run.

## Search Console — TuneUp pattern

Copy `tuneupprecision/public/google79bd43f041dd2a84.html` verbatim into `public/google79bd43f041dd2a84.html`. Body must be exactly:

```
google-site-verification: google79bd43f041dd2a84.html
```

That file is the HTML-file method for the same Google account that already verifies TuneUp, NRAPA, SAPRF, Ranyati, and rdmdev.

Also support the meta-tag method, same as TuneUp `config/services.php`:

```
GOOGLE_SITE_VERIFICATION=
```

When set, `x-seo-meta` renders `<meta name="google-site-verification" content="…">`. File and meta can both be present.

After deploy, add the `shootingsports.co.za` property in Search Console and submit `https://shootingsports.co.za/sitemap.xml`.

## SEO package

Port TuneUp’s `x-seo-meta` into this app, adapted:

- Title: `{page} · Shooting Sports` (keep the existing suffix; do not add a second tagline)
- Description: page prop, else the current homepage pitch
- Canonical: explicit prop, else the current URL **without** the query string (calendar filters must not become share URLs)
- `og:site_name` Shooting Sports, `og:locale` en_ZA, `og:type` website (or `article` only if a page asks)
- `og:image` / Twitter `summary_large_image` from the page image, else `public/images/og-default.png` (1200×630, gunmetal + brass reticle, “Shooting Sports — The SA Register”)
- Optional `GOOGLE_SITE_VERIFICATION` meta
- Always emit WebSite + SportsOrganization JSON-LD for the register (name, url, logo, areaServed ZA). Keep the existing per-page Event / club / range / supplier graph as a second script
- Sitemap `<link rel="alternate" type="application/xml">` in the head

`x-layouts.public` uses the component. Embed layout stays `noindex` and has no OG/Umami.

### Sitemap pages child

Add `/sitemaps/pages.xml` to the index for the static public URLs that are currently invisible to Google: home, calendar, disciplines index, clubs index, ranges index, suppliers index, claim, embed docs, advertise, contact, privacy.

Do not add `/desk`, `/admin`, `/my-calendar`, thank-you, or ical/oembed URLs.

### robots.txt

```
User-agent: *
Disallow: /desk
Disallow: /admin
Disallow: /my-calendar

Sitemap: https://shootingsports.co.za/sitemap.xml
```

### llms.txt

`GET /llms.txt` — `text/plain`, public facts only. Pitch, then links to calendar, disciplines, clubs, ranges, suppliers, advertise, claim, embed, contact, privacy, and the XML sitemap. Tell crawlers desk/admin are not sources. No member names, no emails from listings beyond what the HTML already shows. No `llms-full.txt` in this pass.

## Testing

- `GET /google79bd43f041dd2a84.html` is 200 and the exact TuneUp body
- Home has `og:title`, `og:image`, `twitter:card`, WebSite JSON-LD
- A match page still has Event JSON-LD **and** the site graph
- `robots.txt` lists the sitemap and Disallows `/desk`
- `/llms.txt` is 200 and contains the calendar URL
- `/sitemaps/pages.xml` lists `/calendar` and does not list `/desk`
- Umami `<script>` is absent when env is empty, present when both vars are set
- Embed calendar HTML has `noindex` and no Umami script

## Non-goals

- Hosting Umami inside `docker-compose.yml` for this app
- Google Analytics
- Cookie consent banner
- Per-listing SEO title/description fields in Filament
- Tracking desk or embed iframes
