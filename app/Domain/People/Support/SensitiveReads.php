<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;

/**
 * Audits reads of sensitive fields (ADR-0007): one `sensitive / viewed` entry per employee and
 * request listing the field keys shown, never the values.
 */
final class SensitiveReads
{
    /** @var array<int, array<string, true>> employee id => field keys already logged */
    private array $logged = [];

    /**
     * @param  list<string>  $fieldKeys
     */
    public function record(?User $viewer, Employee $employee, array $fieldKeys): void
    {
        $new = array_values(array_filter($fieldKeys, fn (string $key): bool => ! isset($this->logged[$employee->id][$key])));

        if ($new === []) {
            return;
        }

        foreach ($new as $key) {
            $this->logged[$employee->id][$key] = true;
        }

        activity('sensitive')
            ->causedBy($viewer)
            ->performedOn($employee)
            ->event('viewed')
            ->withProperties(['fields' => $new])
            ->log('Sensitive fields viewed');
    }
}
