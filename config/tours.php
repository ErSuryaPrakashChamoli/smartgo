<?php

/*
 * Tour scheduling (FR-BK-2, BR-TR-1 to BR-TR-4). SmartGro's tour rules are not decided (D-28): staff
 * enter each property's tour hours, slot length and parallel tours themselves, and nothing is assumed
 * here. Whether a visitor's request is confirmed at once is part of the open booking-scope decision
 * (D-07), so it stays off: staff confirm each request (the approved tour journey).
 */
return [

    /** How many days ahead visitors can pick a time: API_CONTRACTS §2.3's longest range. */
    'window_days' => 14,

    /** Notice a tour needs before it starts, in minutes. Not decided (D-28): only future times are offered. */
    'minimum_notice_minutes' => null,

    /** People per tour request (API_CONTRACTS §3.2). */
    'max_visitors' => 10,

    /** D-07: confirm a visitor's request at once instead of waiting for staff. Off until decided. */
    'confirm_on_request' => (bool) env('TOURS_CONFIRM_ON_REQUEST', false),

];
