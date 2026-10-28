<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 3D Model Limits
    |--------------------------------------------------------------------------
    |
    | Hard limits a GLB file must stay within before it is stored (BR-3D-4,
    | FR-3D-1). The owner approved 15 MB and 500,000 triangles per model
    | (OD-119); the other values are safeguards chosen under that decision.
    | CLIENT_INPUTS_REQUIRED.md C-6.5 is the supplier's specification: change
    | it with any value here. A limit bounds what a file may contain; it does
    | not show that a model meets NFR-PERF-4 (OD-119 c).
    |
    | A megabyte is 1,048,576 bytes. Uploads in progress may be larger than
    | the size limit (config/livewire.php), so an oversized model reaches the
    | checks and gets the plain-language reason.
    |
    */

    'limits' => [

        // Approved by the owner (OD-119 a).
        'bytes' => (int) env('TWIN_MODEL_MAX_MEGABYTES', 15) * 1024 * 1024,
        'triangles' => (int) env('TWIN_MODEL_MAX_TRIANGLES', 500_000),

        // The file's JSON description; read and decoded before anything else.
        'description_bytes' => 4 * 1024 * 1024,

        // Parts, and the pieces drawn separately (one per mesh primitive shown).
        'nodes' => 10_000,
        'draw_calls' => 500,
        'materials' => 100,

        // Embedded images, the longest side of any one, and the pixels of all.
        'textures' => 64,
        'texture_side' => 4096,
        'texture_pixels' => 32 * 1024 * 1024,

        // Vertex and index data once decompressed.
        'geometry_bytes' => 128 * 1024 * 1024,

        // The longest side of the model, in metres (glTF's unit).
        'smallest_metres' => 1,
        'largest_metres' => 1000,

        // Time allowed for all the checks of one file.
        'check_seconds' => 10,

    ],

];
