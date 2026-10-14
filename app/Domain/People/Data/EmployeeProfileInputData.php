<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Enums\Gender;
use App\Domain\People\Enums\MaritalStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Non-sensitive profile fields (screen 8 Overview / Personal). Placement changes go through
 * ChangePlacement; sensitive fields through UpdateSensitiveDetails.
 */
#[TypeScript]
final class EmployeeProfileInputData extends Data
{
    public function __construct(
        public string $nameEn,
        public ?string $nameAr = null,
        public ?string $preferredName = null,
        public ?string $workEmail = null,
        public ?string $workPhone = null,
        public ?string $gender = null,
        public ?string $nationality = null,
        public ?string $maritalStatus = null,
        public ?string $hireDate = null,
        public ?string $probationEndsOn = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'nameEn' => ['required', 'string', 'max:160'],
            'nameAr' => ['nullable', 'string', 'max:160'],
            'preferredName' => ['nullable', 'string', 'max:80'],
            'workEmail' => ['nullable', 'email:strict', 'max:254'],
            'workPhone' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            'maritalStatus' => ['nullable', Rule::enum(MaritalStatus::class)],
            'hireDate' => ['nullable', 'date_format:Y-m-d'],
            'probationEndsOn' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
