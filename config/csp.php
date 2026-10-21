<?php

declare(strict_types=1);

/*
| Content-Security-Policy (Phase 9a). The policy is built by `ContentSecurityPolicy`; these are the
| outside origins it allows and where browsers report violations.
*/
return [
    'enabled' => (bool) env('CSP_ENABLED', true),

    // Map tiles, styles, sprites and glyphs for the geofence map (D33).
    'connect' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CSP_CONNECT_SRC', 'https://tiles.openfreemap.org'))))),

    // Violation reports: the app's own endpoint, or a GlitchTip security endpoint (D21).
    'report_uri' => env('CSP_REPORT_URI', '/hooks/csp-report'),
];
