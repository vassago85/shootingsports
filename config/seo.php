<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public indexability
    |--------------------------------------------------------------------------
    |
    | When false (the default), public pages emit robots noindex,nofollow
    | and robots.txt disallows the whole host. Flip SEO_INDEXABLE=true
    | only when the directory should be crawled. Per-page robots values
    | still win, so a thin listing stays noindex after launch.
    |
    */

    'indexable' => filter_var(env('SEO_INDEXABLE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Thin-content thresholds
    |--------------------------------------------------------------------------
    |
    | Province × discipline landings with fewer than this many clubs,
    | ranges and upcoming matches are rendered but noindexed, and left
    | out of the sitemap. Entity pages use Indexability (location plus
    | a discipline, a contact, or a fixture) rather than this count.
    |
    */

    'landing_min' => (int) env('SEO_LANDING_MIN', 3),

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    */

    'sitemap_chunk' => (int) env('SEO_SITEMAP_CHUNK', 5000),

    'sitemap_ttl' => (int) env('SEO_SITEMAP_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Combination-page threshold
    |--------------------------------------------------------------------------
    |
    | Discipline × province pages, calendar time-window pages, and any
    | directory with fewer than this many upcoming matches render but
    | are noindex,follow and left out of the sitemap. Separate from
    | `landing_min` (which gates supplier province pages by listing
    | count, not match count).
    |
    */

    'min_upcoming' => (int) env('SEO_MIN_UPCOMING', 1),

    /*
    |--------------------------------------------------------------------------
    | IndexNow debounce
    |--------------------------------------------------------------------------
    |
    | A single URL is pinged at most once per this window. A listing
    | that is saved twice within the window only pings the first
    | time. Set to 0 to disable the debounce; the observer still
    | filters out non-indexable records and weekend URLs.
    |
    */

    'indexnow_cooldown_seconds' => (int) env('SEO_INDEXNOW_COOLDOWN', 3600),

    /*
    |--------------------------------------------------------------------------
    | Public query parameters that strip from the canonical
    |--------------------------------------------------------------------------
    |
    | Any query string on the calendar, calendar month, or a directory
    | index is treated as a filter or marketing param and makes the
    | page noindex,follow. The canonical always resolves to the
    | bare route. Listed here for documentation; the code treats any
    | non-empty query as filter state.
    |
    */

    'filter_params' => [
        'q', 'discipline', 'province', 'town', 'division', 'family',
        'novice', 'confirmed', 'weekend', 'kind',
        'from', 'to', 'month', 'radius', 'near', 'lat', 'lng',
        'page', 'sort', 'view',
        'visitors', 'club', 'organisation', 'venue', 'shooter',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'fbclid', 'mc_cid', 'mc_eid',
    ],

];
