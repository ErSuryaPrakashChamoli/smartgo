<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Back Office Path
    |--------------------------------------------------------------------------
    |
    | URL path of the staff back office (Filament panel). Production should use
    | a non-default path, ideally behind Cloudflare Access (decision D-30).
    |
    */

    'path' => env('BACKOFFICE_PATH', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Back Office Time Zone
    |--------------------------------------------------------------------------
    |
    | Times staff enter and read in the back office, such as scheduled
    | publication. The application stores every time in UTC; SmartGro works
    | in India Standard Time.
    |
    */

    'timezone' => env('BACKOFFICE_TIMEZONE', 'Asia/Kolkata'),

];
