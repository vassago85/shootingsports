<?php

/*
 * Public-facing vocabulary. Every human-visible label in the admin
 * and desk panels pulls from this file so Events, Clubs, Ranges,
 * Sports and Industry read the same on the public site, in the
 * sidebar, on resource titles, and in dashboard copy.
 *
 * Model and table names are intentionally not changed. This file
 * decouples the label from the class, so renaming the user-visible
 * word never requires a migration.
 */
return [
    // Events (model: Event). Public word is "match" in some places,
    // "event" everywhere else; we standardise on "event" for admin
    // and desk so the directory, sitemap, and nav all agree.
    'event' => 'event',
    'events' => 'events',
    'events_nav' => 'Events',

    // Clubs & series (model: Organisation). Shooters say "club" —
    // series and federations surface in the same list under the
    // same word, scoped by the Type filter.
    'club' => 'club',
    'clubs' => 'clubs',
    'clubs_nav' => 'Clubs',

    // Ranges (model: Venue). "Venue" is a leftover from the pre-rebuild
    // schema; the public site, SEO, and sitemap all say "range".
    'range' => 'range',
    'ranges' => 'ranges',
    'ranges_nav' => 'Ranges',

    // Sports (model: Discipline). Internal taxonomy; public word
    // across the home page, nav, and copy is "sport".
    'sport' => 'sport',
    'sports' => 'sports',
    'sports_nav' => 'Sports',

    // Industry (model: Provider). "Supplier" / "provider" survive in
    // internal code; the public directory is /industry.
    'industry_listing' => 'industry listing',
    'industry_listings' => 'industry listings',
    'industry_nav' => 'Industry',
];
