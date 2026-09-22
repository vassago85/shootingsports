# Division landing — design (22 Sep 2026)

Approved direction: keep the current homepage hero, ask which division the visitor wants, and put sport lists and sponsored ads on the pages one click in. The public site uses the olive palette below. Brass and gold are not brand colours.

## Colour

The public site is dark. This palette is the default, including when the visitor’s system is set to light. There is no brass light theme on public pages.

| Role | Hex |
| --- | --- |
| Main background | `#0B0D0E` |
| Cards and sections | `#15191B` |
| Borders and secondary areas | `#252B2E` |
| Main text | `#F1F2EE` |
| Secondary text | `#9CA3A5` |
| Accent | `#6B7D3A` |
| Accent hover | `#879A4A` |
| High-contrast button | `#FFFFFF` |

About 85% of the page is near black and graphite, 10% is off-white and grey type, 5% is olive. Olive is the centre of the reticle mark, the weekend button, Search, the active nav underline, and small links such as “open this division”. The wordmark is white. Sign in is a white button with near-black text. The stats strip is graphite with an off-white number, not a solid olive bar. Headlines stay off-white.

The mark’s rings and crosshairs are white. Only the centre dot is olive. The same split applies to the hero reticle.

Filament admin and the match-director desk keep their current theme.

## Landing page

The hero stays as it is today: the register kicker, “Find your sport. Find your club. Find your match.”, the weekend button, the match finder, the quick actions, and the next-30-days rail. The stats strip under the hero stays.

Under the strip, one question: **What are you interested in?** Four links, and nothing else in that block:

- Handgun
- Bolt-action rifle
- Self-loading rifle
- Shotgun

The landing page does not list individual sports. It does not render an ad slot. Air rifle is not one of the four.

## Menu

Desktop and mobile primary nav:

Matches · Calendar · Sports · Clubs · Ranges · Industry

Matches still opens the match list at `/calendar`. Calendar opens the month view at `/calendar/month`. The same List / Month / Map switch on those pages stays.

## Divisions and sports

A division is a firearm group. A sport (today’s discipline) belongs to one or more divisions. Renames change the display name only. Existing slugs stay, so current URLs keep working. 3-Gun and Multigun belong to Handgun, Self-loading rifle, and Shotgun, and appear on each of those division pages. Every other sport belongs to one division.

Air rifle is a fifth division in the data, with the four sports already on the register (10m Air Rifle, Field Target, Hunter Field Target, Benchrest Airgun). It is not linked from the landing page or the primary nav until a later change turns it on.

### Handgun

Shown first: IPSC, IDPA, Steel, Training.

IPSC is the existing IPSC Practical record, renamed. IDPA and Training are new. Steel is the existing Steel Challenge record, renamed. Pin Shooting stays its own sport and is listed after those four, with Sport Pistol, Target Pistol, and Action / Defensive. Existing URLs keep their slugs.

### Bolt-action rifle

Precision Rifle, PR22, NRL Hunter, Gong Shooting, F-Class, Benchrest, Big Bore, ELR, Target Rifle, Bisley & Fullbore, Hunting Rifle, Metallic Silhouette.

Big Bore is new. The others already exist and move off the single “Rifle” family onto this division. Service Rifle and Combat Rifle do not.

### Self-loading rifle

IPSC Rifle, 3-Gun, Service Rifle, Combat Rifle, PCC, Multigun.

IPSC Rifle and PCC are new. 3-Gun and Multigun also appear under Handgun and Shotgun.

### Shotgun

English Sporting, FITASC, Down the Line, Trap, Skeet, Compak, 3-Gun, Multigun.

English Sporting is the existing Sporting Clays record, renamed. Compak is the existing Compak / Five-Stand record, renamed. FITASC and Down the Line are new. Wingshooting keeps its URL and is not shown on the Shotgun division page.

## Division page

Route: `/divisions/{slug}` with slugs `handgun`, `bolt-action-rifle`, `self-loading-rifle`, `shotgun`. The air-rifle slug exists and returns 404 while that division is off.

The page lists that division’s published sports. Each card shows the name, the short description, the organisation short name when one is set, and a link to the sport page. A labelled sponsor for that division sits on this page. A Handgun sponsor is the only sponsor here.

Below the sports, the page lists clubs, ranges, upcoming matches, and suppliers whose sports include this division. Each group links through to the existing directory with the division already applied.

## Sport page

The existing discipline page at `/disciplines/{slug}`.

- Opening line is the short description already stored on the sport.
- One organisation line. The text is the organisation’s short name (SAPRF, CTSASA). The link is that organisation’s website, opened in a new tab. If there is no website, the name links to the organisation’s page on the register. If no organisation is set, the line is absent.
- The full write-up follows.
- Then up to four YouTube videos. Staff add a title and a YouTube URL in the admin. The thumbnail is built from the video id. The link opens YouTube in a new tab and is labelled as leaving the site. A sport with no saved videos has no video row.
- Then the clubs, ranges, upcoming matches, and suppliers already tagged to that sport.
- A labelled sponsor for each division the sport belongs to, one per division, at most three. 3-Gun can therefore show a Handgun sponsor, a Self-loading sponsor, and a Shotgun sponsor. A single-division sport shows one.

## Clubs, ranges, matches, suppliers

These records stay tagged to sports, as they are today. A listing belongs to every division of its sports. Division pages and the existing directories can filter by division. On a club, range, match, or supplier page, show the sponsor for the divisions of that listing, one per division, at most two.

## Advertising

The home page renders no ad placement.

A division sponsorship is a placement tied to one division. It is eligible on that division’s page, on sports in that division, and on listings whose sports are in that division. It is not eligible on another division’s pages. The existing calendar leaderboard stays on `/calendar`, which is not the landing page. Home leaderboard inventory is not rendered.

Placements with no division keep today’s page-and-slot behaviour, except that the home page does not render them.

## Admin

On a sport, staff edit the short description, the write-up, the divisions (more than one allowed), the governing organisation, and up to four videos. Each video is a title plus a URL on `youtube.com` or `youtu.be`. Any other URL is rejected and not saved. The public page never shows a video that failed that check.

## Data

Add a `Division` enum: Handgun, BoltActionRifle, SelfLoadingRifle, Shotgun, AirRifle. Add a pivot from disciplines to divisions so one sport can sit in several. Migrate the current single family as follows:

- Handgun family → Handgun
- Shotgun family → Shotgun
- Airgun family → AirRifle
- Rifle family → BoltActionRifle, except Service Rifle and Combat Rifle → SelfLoadingRifle
- Multi family (3-Gun, Multigun) → Handgun, SelfLoadingRifle, and Shotgun

The calendar family filter becomes these divisions. Air rifle is omitted from that filter while the division is off. New code reads the pivot. The existing `family` column stays in sync as a single value: the sport’s only division, or `multi` when it belongs to more than one.

Add a `division` value on advertising placements, nullable. Null means the placement is not division-targeted.

Add a small videos table: discipline id, title, url, video id, sort order. Maximum four rows per sport, enforced in validation.

## Empty and failure states

- A division with no published sports still renders, with an empty sport list and no sponsor when none is booked.
- A YouTube URL that is not a watch, short, or share link is a validation error on save.
- A sport with a governing organisation and a blank website uses the on-site organisation page.
- Air rifle routes 404 until the division is enabled. Its sports remain reachable at their existing discipline URLs.

## Tests

- Home shows the four division links, the existing hero, and no ad slot.
- Primary nav includes Calendar pointing at the month view, on desktop and in the mobile menu.
- The Handgun page lists IPSC, IDPA, Steel, and Training, and does not list Precision Rifle.
- 3-Gun is listed on Handgun, Self-loading rifle, and Shotgun.
- The air-rifle division URL 404s, and the landing page does not link to it.
- A sport with no videos renders no video row. A saved YouTube URL renders a thumbnail link. A non-YouTube URL is rejected.
- The organisation line uses the short name and the website when both exist.
- A Handgun sponsor is returned for the Handgun page and is not returned for the Shotgun page.

## Out of scope

Changing the hero layout or copy. Enabling air rifle in the nav. Taking payment inside the ad slot. Merging Wingshooting into Shotgun. Rewriting the match finder. Restyling Filament admin or the match-director desk. The burnt-orange alternative palette.
