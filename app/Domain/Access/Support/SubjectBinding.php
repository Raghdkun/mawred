<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * How a scoped record reaches the person it is about (ADR-0005): a column holding the subject's
 * user id, a column holding the subject's employee id, or both. Reference data (branches,
 * departments…) has no subject: only an unrestricted grant sees it, anything narrower fails closed.
 *
 * Records that belong to a place rather than a person (a requisition and its candidates) bring
 * their own constraint: given a narrower grant, it limits the query to what that grant covers.
 */
final readonly class SubjectBinding
{
    /**
     * @param  (Closure(Builder<covariant Model>, User, Grant): void)|null  $custom
     */
    private function __construct(
        public ?string $userColumn,
        public ?string $employeeColumn,
        public bool $isReference = false,
        public ?Closure $custom = null,
    ) {
        if (! $isReference && $custom === null && $userColumn === null && $employeeColumn === null) {
            throw new InvalidArgumentException('A subject binding needs a user or an employee column.');
        }
    }

    /**
     * @param  Closure(Builder<covariant Model>, User, Grant): void  $constrain
     */
    public static function custom(Closure $constrain): self
    {
        return new self(null, null, custom: $constrain);
    }

    public static function user(string $column): self
    {
        return new self($column, null);
    }

    public static function employee(string $column): self
    {
        return new self(null, $column);
    }

    public static function reference(): self
    {
        return new self(null, null, isReference: true);
    }
}
