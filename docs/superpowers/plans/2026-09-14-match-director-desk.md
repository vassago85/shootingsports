# Match Director Desk Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Ship a branded `/desk` Filament panel where match directors register, create draft clubs/series, claim published listings, and manage their events — matching the public site aesthetic.

**Architecture:** Second Filament panel (`desk`) alongside staff `admin`. Desk resources are scoped by `organisation_user`. Public calendar filters out events whose host is not published. Shared Event/Organisation form patterns; desk omits staff-only fields (source, verification, publish).

**Tech Stack:** Laravel 12, Filament 5, Livewire 4, existing `media` disk, Pest.

## Global Constraints

- Desk path: `/desk` (login + register)
- Admin stays `is_staff` only
- Draft org status: `pending` (not public until staff sets `published`)
- Match director role: `OrganisationUserRole::MatchDirector`
- Theme tokens: gunmetal `#232a2c` / `#101516`, brass `#d9ae52` / `#b3892b`, fonts Saira Condensed + IBM Plex
- Banner/logo files → `media` disk → `/mnt/storage/shootingsports/media` on prod

---

### Task 1: Series type + org logos + policy

- [ ] Add `OrganisationType::Series`
- [ ] Finish `logo_path` migration/model/admin form/public display (if incomplete)
- [ ] OrganisationPolicy: authenticated users can `create`; MatchDirector can `update` own org; desk cannot `delete`/`publish`
- [ ] Event `scopeUpcoming` / public queries: require host organisation `published`
- [ ] Tests for policy + calendar filter

### Task 2: Desk panel + access + theme

- [ ] `DeskPanelProvider` at `/desk` with login + registration
- [ ] `User::canAccessPanel`: admin → staff; desk → any authenticated user
- [ ] Custom theme CSS (`resources/css/filament/desk/theme.css`) + Vite entry
- [ ] Brand name, reticle favicon, remove FilamentInfoWidget

### Task 3: Desk resources + dashboard

- [ ] Desk Organisation resource (create draft + edit own; logo upload; no publish control)
- [ ] Desk Event resource (scoped to member orgs; simplified form)
- [ ] Desk Claim page/action (search published org → submit claim)
- [ ] Custom Dashboard: empty state + CTAs matching public aesthetic
- [ ] Feature tests: register, create series, create event, claim pending

### Task 4: Public nav + deploy notes

- [ ] Link “For Clubs” / desk login from public layout
- [ ] Smoke with browser if available
- [ ] Commit and push
