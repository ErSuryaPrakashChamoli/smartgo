<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Only these settings differ from Livewire's defaults; every other Livewire
    | option keeps its packaged value. A 3D model may be up to 15 MB (NFR-PERF-4),
    | above Livewire's default 12 MB limit for an upload in progress. Files up to
    | 32 MB reach the automated checks, so a model that is too large gets the
    | plain-language reason (FR-3D-1). Each form still sets its own limit (images:
    | 10 MB). The server's upload_max_filesize and post_max_size must allow it.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:32768'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | CSP Safe
    |--------------------------------------------------------------------------
    |
    | Off, as in Livewire's defaults. With it on, Livewire ships the build of
    | Alpine that never evaluates expressions as strings, and the back office's
    | Content Security Policy drops 'unsafe-eval' (SetSecurityHeaders). Filament
    | 5 does not work with that build: on the test server 26 of 27 back-office
    | screens threw "CSP Parser Error" and menus, filters, saving and uploads
    | stopped (P14-16, OD-158). Keep it off; it exists only to repeat that check
    | on a test server after a Filament upgrade.
    |
    */

    'csp_safe' => (bool) env('LIVEWIRE_CSP_SAFE', false),

];
