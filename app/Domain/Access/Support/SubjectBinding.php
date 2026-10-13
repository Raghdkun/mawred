<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use InvalidArgumentException;

/**
 * How a scoped record reaches the person it is about (ADR-0005): a column holding the subject's
 * user id, a column holding the subject's employee id, or both.
 */
final readonly class SubjectBinding
{
    private function __construct(
        public ?string $userColumn,
        public ?string $employeeColumn,
    ) {
        if ($userColumn === null && $employeeColumn === null) {
            throw new InvalidArgumentException('A subject binding needs a user or an employee column.');
        }
    }

    public static function user(string $column): self
    {
        return new self($column, null);
    }

    public static function employee(string $column): self
    {
        return new self(null, $column);
    }
}
