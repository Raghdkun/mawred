<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

/**
 * An Update Record action (e.g. apply an approved profile change). Implementations call the
 * owning domain's Action and must be idempotent per step (the step ULID is the key).
 */
interface RecordUpdate
{
    public function key(): string;

    /** @return array{en: string, ar: string} */
    public function label(): array;

    /** The roadmap phase that ships the target domain; 0 = available now (D55 flags the rest). */
    public function availableInPhase(): int;

    public function apply(StepContext $context): void;
}
