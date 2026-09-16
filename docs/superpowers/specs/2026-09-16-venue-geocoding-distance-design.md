# Venue pins + real distance (16 Sep 2026)

Approved: hybrid Nominatim geocoding + staff pin lock; restore radius (town + browser geo); venue pins on `/map`. Google Geocoding is a later swap behind the same interface.

## Decisions
- Scope: geocode + distance filter + map pins
- Geocode: Nominatim first; `geocode_source=staff` never overwritten
- Distance: town search default + optional “Use my location”
- Map: venue-level pins with upcoming match counts

## Out of scope
- Road routing / drive time
- Google Geocoding (interface ready)
- Provider geocoding
