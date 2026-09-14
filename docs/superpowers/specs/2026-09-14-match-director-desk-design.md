# Match Director Desk — Design

**Date:** 2026-09-14  
**Status:** Approved for build (user: hybrid + events/listing + front-page aesthetic)

## Goal

Give match directors a place to register, create or claim a club/series, edit that listing, and manage their own events — without staff access to `/admin`.

## Decisions

| Decision | Choice |
|---|---|
| Access model | Hybrid: create draft listing immediately; claim published listing needs staff approval |
| Scope | Events + own listing (name, logo, description, contact) |
| UI shell | Second Filament panel at `/desk`, themed to match public site (gunmetal / brass / Saira / IBM Plex) |
| Series | New `OrganisationType::Series` for branded recurring matches (e.g. Royal Flush) |
| Public calendar | Events whose host organisation is not `published` stay off the public calendar |
| Logos | Clubs/federations/series upload logos to the `media` disk (extra HDD) |

## Actors

- **Staff** (`is_staff`): `/admin` only for full site control; may also use `/desk`
- **Match director**: `/desk` only; scoped to organisations they belong to

## Flows

1. Register at `/desk/register` → empty dashboard
2. **Create listing** → status `pending`, creator = match_director on pivot → edit listing + add events
3. **Claim listing** → pending Claim → staff approves in `/admin` → membership granted
4. Staff sets organisation `status` to `published` → public page + hosted events eligible for calendar

## Non-goals (v1)

- Custom Livewire portal (revisit if Filament theme is not enough)
- Venue/provider self-service
- Multi-member invite UI (staff can still attach via admin)
