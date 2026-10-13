<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Write-side field permissions (ADR-0007). System actors (jobs, imports run by the platform)
 * are trusted; people need `write` on the field key through one of their roles.
 */
final readonly class FieldGuard
{
    public function __construct(private ScopeResolver $resolver) {}

    public function canWrite(Actor $actor, string $fieldKey): bool
    {
        if (! $actor->user instanceof User) {
            return true;
        }

        return $this->resolver->fieldAccess($actor->user, $fieldKey) === FieldAccess::Write;
    }

    public function canRead(Actor $actor, string $fieldKey): bool
    {
        if (! $actor->user instanceof User) {
            return true;
        }

        return $this->resolver->fieldAccess($actor->user, $fieldKey) instanceof FieldAccess;
    }

    public function assertCanWrite(Actor $actor, string $fieldKey, string $inputField): void
    {
        if (! $this->canWrite($actor, $fieldKey)) {
            throw new DomainRuleViolation(
                'people.field_forbidden',
                __('You do not have permission to change this field.'),
                [$inputField => [__('You do not have permission to change this field.')]],
                403,
            );
        }
    }
}
