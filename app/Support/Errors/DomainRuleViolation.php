<?php

declare(strict_types=1);

namespace App\Support\Errors;

use RuntimeException;

/**
 * A business rule was broken (overlap, insufficient balance, invalid transition…).
 *
 * The `errorCode` is stable and documented (e.g. `leave.insufficient_balance`); the message is
 * already localised. Rendered as the API error envelope or flashed back to Inertia forms.
 */
class DomainRuleViolation extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $details  field => messages
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
