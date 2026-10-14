<?php

declare(strict_types=1);

/*
| Local demo and volume data (database/seeders/Demo, database/seeders/Performance). Never used in
| production; the seeders refuse to run there.
*/
return [
    'demo_password' => env('DEMO_PASSWORD'),
    'performance' => [
        'branches' => (int) env('PERF_BRANCHES', 50),
        'employees' => (int) env('PERF_EMPLOYEES', 5000),
    ],
];
