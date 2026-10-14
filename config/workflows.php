<?php

declare(strict_types=1);

return [
    /*
    | Who gets a task when an assignee rule resolves nobody (architecture §6). The instance is
    | flagged on its timeline so an admin can fix the rule.
    */
    'fallback_role' => env('WORKFLOWS_FALLBACK_ROLE', 'hr_admin'),

    /*
    | Link approvals (ADR-0009, D24): how long an emailed approve/reject link stays valid.
    */
    'action_token_ttl_hours' => (int) env('WORKFLOWS_ACTION_TOKEN_TTL_HOURS', 72),
];
