<?php

/*
 * Values the availability rules need (BUSINESS_RULES §2). Both are UNCONFIRMED: BR-AV-2's 90-day
 * horizon is a [DEFAULT] the owner confirms at the Phase 5 gate (D-36), and BR-AV-1 gives no threshold
 * for "Limited availability", so none is set and that state is never shown. Customer-facing states are
 * computed for staff only until the website shows availability (FR-DISC-3, owner action A-8).
 */
return [

    /** BR-AV-2: how far ahead "Available from {date}" may look, in days. [DEFAULT], unconfirmed (D-36). */
    'availability_horizon_days' => 90,

    /** BR-AV-1: shared zones with fewer free seats than this show "Limited availability". Not decided. */
    'limited_availability_below' => null,

    /**
     * BR-PROP-3's inventory part: a property version is published (or published again) only when the
     * property has a floor and a listable space or an enquiry-only offering. Off until the owner switches
     * it on under the transition plan (OD-128 b, OD-129 b, PHASE_5_ACCEPTANCE_PACK §15.2), one
     * environment at a time once `php artisan inventory:floor-rule-readiness` passes there. Switching it
     * on never takes a published property off the website; it refuses only later publications. Switching
     * it off again (and refreshing the configuration cache) is the whole rollback: the rule writes nothing.
     */
    'enforce_floor_rule_on_publication' => (bool) env('INVENTORY_ENFORCE_FLOOR_RULE', false),

];
