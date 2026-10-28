<?php

declare(strict_types=1);

return [
    /*
    | The roadmap phase this build has shipped. Things owned by later phases (request types such
    | as annual leave, Update Record actions, setup steps) stay hidden until it reaches them (D55).
    */
    'phase' => 3,

    /*
    | The careers site (D87) is served at /careers on the app host. Turning on
    | CAREERS_ON_OWN_HOST moves it to CAREERS_HOST (careers.<domain>, D11) without code changes.
    */
    'careers_host' => env('CAREERS_ON_OWN_HOST', false) ? env('CAREERS_HOST') : null,

    /*
    | Server alerts (ADR-0019): the host monitor and backup scripts email these addresses through
    | `ops:alert`. Comma-separated. The external uptime ping covers the case where the app is down.
    */
    /*
    | Where browsers reach Reverb, read at runtime (the image is built once for every host). The
    | app itself may publish to Reverb on the private network (REVERB_HOST=reverb in production).
    */
    'realtime' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_BROWSER_HOST', env('REVERB_HOST')),
        'port' => (int) env('REVERB_BROWSER_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('REVERB_BROWSER_SCHEME', env('REVERB_SCHEME', 'https')),
    ],

    'ops' => [
        'alert_emails' => env('OPS_ALERT_EMAILS'),
    ],
];
