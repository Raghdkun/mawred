<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PersonRefData extends Data
{
    public function __construct(
        public string $id,
        public string $nameEn,
        public ?string $nameAr,
        public ?string $preferredName,
    ) {}

    public static function of(?Employee $employee): ?self
    {
        return $employee instanceof Employee ? new self($employee->ulid, $employee->name_en, $employee->name_ar, $employee->preferred_name) : null;
    }
}
