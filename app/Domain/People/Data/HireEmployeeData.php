<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Enums\Gender;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Add an employee (screen 7 "Add employee", import rows, hiring hand-off). The employee number is
 * generated unless given (D38); sensitive fields are set afterwards, behind field permissions.
 */
#[TypeScript]
final class HireEmployeeData extends Data
{
    public function __construct(
        public string $nameEn,
        public string $hireDate,
        public PlacementInputData $placement,
        public ?string $nameAr = null,
        public ?string $preferredName = null,
        public ?string $employeeNumber = null,
        public ?string $workEmail = null,
        public ?string $workPhone = null,
        public ?string $gender = null,
        public ?string $nationality = null,
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
            'employeeNumber' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'workEmail' => ['nullable', 'email:strict', 'max:254'],
            'workPhone' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            'hireDate' => ['required', 'date_format:Y-m-d'],
            'probationEndsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:hireDate'],
        ];
    }
}
