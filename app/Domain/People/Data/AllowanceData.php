<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\EmployeeAllowance;
use App\Support\Money\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An allowance on the profile (D116), for people with the salary field permission. */
#[TypeScript]
final class AllowanceData extends Data
{
    /**
     * @param  array{minor: int, currency: string}|null  $amount
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $label,
        public ?array $amount,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public ?string $note,
    ) {}

    public static function fromAllowance(EmployeeAllowance $allowance): self
    {
        return new self(
            $allowance->ulid,
            $allowance->type->value,
            $allowance->label,
            $allowance->amount instanceof Money ? ['minor' => $allowance->amount->minor, 'currency' => $allowance->amount->currency] : null,
            $allowance->effective_from->toDateString(),
            $allowance->effective_to?->toDateString(),
            $allowance->note,
        );
    }
}
