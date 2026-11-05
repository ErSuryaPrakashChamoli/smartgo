<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (P14-16, SECURITY_MODEL §5)
    |--------------------------------------------------------------------------
    |
    | "off" sends no policy. "report" sends the policy as Content-Security-Policy-Report-Only: browsers
    | block nothing and only report what the policy would block, so the policy can be checked against every
    | page before it is enforced. Enforcement is not available yet: it is a reviewed change the owner
    | approves (PHASE_14_ACCEPTANCE_PACK §9 item 8). Any other value counts as "off".
    |
    */

    'csp_mode' => env('CSP_MODE', 'off'),

];
