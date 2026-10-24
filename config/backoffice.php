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

];
