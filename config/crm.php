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

    /*
    |--------------------------------------------------------------------------
    | First response (FR-CRM-1, BR-CRM-4, P9-C)
    |--------------------------------------------------------------------------
    |
    | The back office always shows how long a new enquiry has waited for its
    | first response, in plain time since it arrived. A target ("due by",
    | "overdue") needs two owner decisions (D-09, A-41): the target in minutes
    | (BR-CRM-4's 15 minutes is a [DEFAULT], not approved) and which hours
    | count: "calendar" (every minute) or "location_hours" (only the enquiry's
    | location's opening hours, in India time). Until both are set, no target
    | is shown and nothing is called overdue. Nothing is ever sent (O-1).
    |
    */

    'first_response' => [
        'target_minutes' => null,
        'clock' => null,
    ],

];
