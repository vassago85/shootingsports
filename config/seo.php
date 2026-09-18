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

];
