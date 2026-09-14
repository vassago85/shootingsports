<?php

use App\Imports\MpsaCalendarImporter;
use App\Imports\SaprfCalendarImporter;

return [
    [
        'key' => 'saprf',
        'name' => 'South African Precision Rifle Federation',
        'url' => 'https://saprf.co.za/events?view=table',
        'mode' => 'import',
        'importer' => SaprfCalendarImporter::class,
        'notes' => 'Public HTML table with date, title, discipline, province and venue.',
    ],
    [
        'key' => 'mpsa',
        'name' => 'Mpumalanga Practical Shooting Association',
        'url' => 'https://mpsa.net.za/calendar26.html',
        'mode' => 'import',
        'importer' => MpsaCalendarImporter::class,
        'notes' => 'Static HTML table for the season. Dates are a bit messy (31/01) but parseable.',
    ],
    [
        'key' => 'ctsasa',
        'name' => 'Clay Target Shooting Association of South Africa',
        'url' => 'https://ctsasa.co.za/competition-calendar/',
        'mode' => 'sell',
        'notes' => 'WordPress + PDF year planner. Nationals are already seeded by hand. Club shoots need the embed.',
    ],
    [
        'key' => 'sapsa',
        'name' => 'South African Practical Shooting Association (IPSC)',
        'url' => 'https://sapsa.co.za/calendar',
        'mode' => 'sell',
        'notes' => 'Calendar is a graphic, not a table. Sell them the embed.',
    ],
    [
        'key' => 'nrapa',
        'name' => 'National Rifle and Pistol Association',
        'url' => 'https://nrapa.co.za',
        'mode' => 'sell',
        'notes' => 'Membership and dedicated-status, not a public match feed. Sell the embed to their clubs.',
    ],
    [
        'key' => 'idpa',
        'name' => 'IDPA South Africa',
        'url' => 'https://www.idpa.com/matches/',
        'mode' => 'sell',
        'notes' => 'Global IDPA board, almost no current SA listings. Clubs should embed their own calendar.',
    ],
    [
        'key' => 'sahunters',
        'name' => 'SA Hunters (SA Jagters)',
        'url' => 'https://sahunters.co.za/shooting/2026-streekskietprogram-regional-shooting-program/',
        'mode' => 'sell',
        'notes' => 'Year planner is a SharePoint PDF/XLSX, not a public table. Member GlobalCalendar mixes AGMs with branch shoots. Sell them the embed; nationals can be typed in by staff.',
    ],
    [
        'key' => 'nrlhunter',
        'name' => 'NRL Hunter South Africa',
        'url' => 'https://www.nrlhuntersa.org/index.php/2027-season-matches',
        'mode' => 'sell',
        'notes' => 'Joomla poster cards: title and town are text, dates are screenshots. Some ENTER buttons go to PractiScore. Sell them the embed; staff can type the season once.',
    ],
    [
        'key' => 'send-it-elr',
        'name' => 'Send It ELR',
        'url' => 'https://shootingsports.co.za/clubs/send-it-elr',
        'mode' => 'sell',
        'notes' => 'Already on the register. They should use the club embed on their own site.',
    ],
];
