# Calendar Month + Venue Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship public month calendar (List|Month|Map), Filament venue merge with duplicate suggestions, and pasteable lat/lng.

**Architecture:** Reuse `PublicEventQuery` + `CalendarFilter` for month chips; new `VenueMerger` / `VenueDuplicateFinder` / `CoordinatePaste` services for admin; Filament wiring only — no new schema.

**Tech Stack:** Laravel 12, Livewire, Filament 5, Pest, Blade/CSS (existing public design tokens).

## Global Constraints

- Upcoming matches only (`Event::upcoming()` / `PublicEventQuery`)
- Loser venues → `ListingStatus::Archived` (not hard-delete)
- Staff pins never overwritten by merge/geocode
- Paste format: `-25.95, 28.48` (comma/space, optional °)
- Match existing public CSS language (view-toggle, result-bar, etc.)
- No Google Geocoding in this slice

## File map

| File | Role |
| --- | --- |
| `app/Support/CoordinatePaste.php` | Parse pasted lat,lng string |
| `app/Services/Venues/VenueMerger.php` | Absorb duplicates into primary |
| `app/Services/Venues/VenueDuplicateFinder.php` | Suggest similar venues |
| `app/Http/Controllers/CalendarMonthController.php` | `/calendar/month` |
| `app/Livewire/CalendarMonth.php` | Month grid + shared filters |
| `resources/views/public/calendar-month.blade.php` | Page shell + toggle |
| `resources/views/livewire/calendar-month.blade.php` | Grid UI |
| `resources/css/app.css` | `.month-cal` styles |
| `app/Filament/.../VenueResource.php` | Paste field, merge bulk, duplicates |
| `app/Filament/Pages/VenueDuplicates.php` | Likely-duplicates page |
| `tests/Feature/CoordinatePasteTest.php` | Parser |
| `tests/Feature/VenueMergeTest.php` | Merge + duplicates |
| `tests/Feature/CalendarMonthTest.php` | Month route + upcoming only |

---

### Task 1: CoordinatePaste

- [ ] Write Pest cases: valid comma, spaces, degree symbols; invalid / out-of-SA optional reject
- [ ] Implement `CoordinatePaste::parse(?string): ?array{lat:float,lng:float}`
- [ ] Run tests green

### Task 2: VenueMerger

- [ ] Write Pest: events move, losers archived, staff pin preserved, empty fields filled
- [ ] Implement `VenueMerger::merge(Venue $primary, Collection $losers): array{events:int,archived:int}`
- [ ] Run tests green

### Task 3: VenueDuplicateFinder

- [ ] Write Pest: same town + similar name groups
- [ ] Implement finder returning groups of Venue models
- [ ] Run tests green

### Task 4: Filament wiring

- [ ] Paste coordinates field on Venue form (dehydrated false; afterStateUpdated fills lat/lng + staff)
- [ ] Bulk merge action + Edit “Merge duplicates” action
- [ ] `VenueDuplicates` Filament page under Directory
- [ ] Feature smoke test or unit coverage via existing merge tests

### Task 5: Month calendar public UI

- [ ] Route `calendar.month` + controller
- [ ] Livewire `CalendarMonth` with `#[Url] month=YYYY-MM`, shared filter props, PublicEventQuery constrained to month
- [ ] Views + List|Month|Map toggle on calendar, month, map
- [ ] CSS for month grid
- [ ] Pest: route 200, chip for upcoming in month, past excluded, toggle links present

### Task 6: Verify + ship

- [ ] `vendor/bin/pest` related + full suite
- [ ] `vendor/bin/pint --dirty`
- [ ] Commit + push when user already said build (include in final commit)
