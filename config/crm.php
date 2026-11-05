<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assignment rules (FR-CRM-2, P9-B)
    |--------------------------------------------------------------------------
    |
    | "Least loaded" gives a new enquiry to the person with the fewest enquiries
    | counted as workload. What counts is the owner's decision (A-34): the
    | enquiry statuses to count, for example every open status. Until it is set
    | here, a least-loaded rule cannot be switched on. Nothing else is assumed.
    |
    */

    'assignment' => [
        'workload_statuses' => null,
    ],

];
