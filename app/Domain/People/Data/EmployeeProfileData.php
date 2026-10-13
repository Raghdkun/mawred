<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Employee profile (screen 8) as one viewer may see it. Built by EmployeeProfiles, which applies
 * field permissions and audits sensitive reads.
 */
#[TypeScript]
final class EmployeeProfileData extends Data
{
    /**
     * @param  array{linked: bool, email: string|null, invitation: array{email: string, expiresAt: string}|null}  $account
     * @param  list<CustomFieldValueData>  $customFields
     */
    public function __construct(
        public EmployeeSummaryData $summary,
        public ?string $gender,
        public ?string $nationality,
        public ?string $maritalStatus,
        public ?string $probationEndsOn,
        public ?string $terminationDate,
        public ?string $terminationReason,
        public ?RefData $employmentType,
        public ?RefData $grade,
        public int $directReportsCount,
        public array $account,
        public SensitiveDetailsData $sensitive,
        public array $customFields,
    ) {}
}
