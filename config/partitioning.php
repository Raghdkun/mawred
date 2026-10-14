<?php

declare(strict_types=1);

/*
| Monthly range-partitioned, append-only tables (ADR-0018). `partitions:maintain` keeps
| `months_ahead` future partitions and drops partitions older than `retention_months`
| (null = keep forever). Retention values are settings-driven in later phases.
*/
return [
    'months_ahead' => 3,

    'tables' => [
        'activity_log' => [
            'column' => 'created_at',
            'retention_months' => env('AUDIT_RETENTION_MONTHS') !== null ? (int) env('AUDIT_RETENTION_MONTHS') : null,
        ],
        'notification_deliveries' => [
            'column' => 'created_at',
            'retention_months' => 12,
        ],
    ],
];
