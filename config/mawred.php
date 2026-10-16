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
];
