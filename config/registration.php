<?php

return [

    /*
    |--------------------------------------------------------------------------
    | New registration notices
    |--------------------------------------------------------------------------
    |
    | These addresses are emailed when someone creates an account on the
    | public signup form. Dirk Pio and Paul. Set
    | REGISTRATION_NOTIFY_EMAILS to a comma-separated list to replace them.
    |
    */

    'notify_emails' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'REGISTRATION_NOTIFY_EMAILS',
            'dirkpio01@gmail.com,paul@charsley.co.za',
        )),
    ))),

];
