<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitewide noindex
    |--------------------------------------------------------------------------
    |
    | When true, public pages emit robots noindex,nofollow unless the view
    | already set a robots value. Defaults to the coming-soon gate so
    | launch is: COMING_SOON=false (and SITE_NOINDEX unset/false).
    |
    */

    'noindex' => env('SITE_NOINDEX') !== null
        ? filter_var(env('SITE_NOINDEX'), FILTER_VALIDATE_BOOLEAN)
        : (bool) env('COMING_SOON', false),

];
