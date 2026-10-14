<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Phone\Phones;

/**
 * Work email / phone rules shared by hire, profile edits and imports.
 */
final class EmployeeContact
{
    public static function email(?string $email, ?Employee $current = null): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = mb_strtolower(trim($email));

        $taken = Employee::withTrashed()
            ->whereRaw('lower(work_email) = ?', [$email])
            ->when($current?->exists, fn ($q) => $q->whereKeyNot($current?->id))
            ->exists();

        if ($taken) {
            throw new DomainRuleViolation('people.email_taken', __('Another employee already uses this work email.'), ['workEmail' => [__('Another employee already uses this work email.')]]);
        }

        return $email;
    }

    public static function phone(?string $phone, ?string $region, string $field = 'workPhone'): ?string
    {
        $e164 = Phones::toE164($phone, $region);

        if ($e164 === false) {
            throw new DomainRuleViolation('people.phone_invalid', __('Enter a valid phone number.'), [$field => [__('Enter a valid phone number.')]]);
        }

        return $e164;
    }
}
