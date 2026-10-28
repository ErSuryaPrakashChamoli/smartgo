<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Only this setting differs from the package's defaults; every other option
    | keeps its packaged value. The package refuses files over 10 MB, but a 3D
    | model may be up to 15 MB (OD-119, config/twin.php). This matches the
    | largest upload the back office accepts in progress (config/livewire.php),
    | so the library never refuses a file its form has accepted; each form keeps
    | its own limit (images: 10 MB, 3D models: config/twin.php).
    |
    */

    'max_file_size' => 32 * 1024 * 1024,

];
