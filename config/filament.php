<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Component cache
    |--------------------------------------------------------------------------
    |
    | Only this setting differs from Filament's defaults. Filament keeps the
    | pages and resources it discovered in this folder (written by
    | `php artisan filament:cache-components`, part of `optimize`), and web
    | requests use that list instead of the working tree. A server that must
    | show the working tree's screens, such as the browser kit's, points it at
    | a folder that does not exist, as it does for the configuration and route
    | caches. Default: unchanged.
    |
    */

    'cache_path' => env('FILAMENT_CACHE_PATH', base_path('bootstrap/cache/filament')),

];
