<?php

declare(strict_types=1);

namespace App\Domain\Audit\Contracts;

/**
 * Implemented by auditable models that hold sensitive values (ADR-0007). Listed attributes are
 * still recorded as changed, but their values are replaced with a mask.
 */
interface MasksAuditedAttributes
{
    /**
     * @return list<string>
     */
    public function auditMaskedAttributes(): array;
}
