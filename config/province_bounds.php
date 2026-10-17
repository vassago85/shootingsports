<?php

/*
 * Loose bounding boxes for the nine South African provinces, used
 * by VenueResource to warn (not block) when a pinned lat/lng falls
 * outside the chosen province. Boxes are intentionally generous —
 * enough to catch "Taai Boskraal is in the Western Cape" level
 * mistakes without false-flagging venues near a province border.
 *
 * Keys match App\Enums\Province values.
 */
return [
    'eastern_cape' => ['min_lat' => -34.5, 'max_lat' => -30.0, 'min_lng' => 22.0, 'max_lng' => 30.5],
    'free_state' => ['min_lat' => -30.8, 'max_lat' => -26.5, 'min_lng' => 24.0, 'max_lng' => 29.9],
    'gauteng' => ['min_lat' => -26.9, 'max_lat' => -25.1, 'min_lng' => 27.1, 'max_lng' => 29.0],
    'kwazulu_natal' => ['min_lat' => -31.5, 'max_lat' => -26.8, 'min_lng' => 28.2, 'max_lng' => 33.0],
    'limpopo' => ['min_lat' => -25.6, 'max_lat' => -22.0, 'min_lng' => 26.0, 'max_lng' => 31.7],
    'mpumalanga' => ['min_lat' => -27.3, 'max_lat' => -24.4, 'min_lng' => 28.2, 'max_lng' => 32.1],
    'northern_cape' => ['min_lat' => -33.0, 'max_lat' => -26.0, 'min_lng' => 16.3, 'max_lng' => 25.5],
    'north_west' => ['min_lat' => -28.0, 'max_lat' => -24.3, 'min_lng' => 22.6, 'max_lng' => 28.4],
    'western_cape' => ['min_lat' => -35.0, 'max_lat' => -30.5, 'min_lng' => 17.5, 'max_lng' => 24.3],
];
