<?php

/*
 * The price units each workspace category may use: the options of the approved pricing structure
 * (PRICING_CONFIGURATION_PROPOSAL §2, OD-35). Finance chooses which of them to use when entering plans
 * (PR-1). Managed offices are priced by quotation only, so they have no price plan unit. A category not
 * listed here cannot be given a price plan until its units are added. No price is set anywhere in
 * configuration (BR-PR-1).
 */
return [

    'units_by_category' => [
        'private_office' => ['per_cabin_month', 'per_seat_month'],
        'dedicated_desk' => ['per_seat_month'],
        'hot_desk' => ['per_day', 'per_seat_month', 'bundle_days'],
        'meeting_room' => ['per_hour', 'per_half_day', 'per_day'],
        'conference_room' => ['per_hour', 'per_half_day', 'per_day'],
        'virtual_office' => ['per_month_service'],
        'managed_office' => [],
    ],

    /** Prices are stored tax-exclusive (PRICING_CONFIGURATION_PROPOSAL §1.3); "inclusive" waits for D-04. */
    'tax_treatment' => 'exclusive',

    'currency' => 'INR',

];
