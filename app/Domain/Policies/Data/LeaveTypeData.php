<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use App\Domain\Policies\Models\LeaveType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LeaveTypeData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public string $unit,
        public bool $isPaid,
        public bool $allowsHalfDays,
        public ?string $eligibleGender,
        public bool $requiresAttachment,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public static function fromType(LeaveType $type): self
    {
        /** @var array<string, string> $name */
        $name = $type->getTranslations('name');

        return new self(
            id: $type->ulid,
            code: $type->code,
            name: $name,
            unit: $type->unit,
            isPaid: $type->is_paid,
            allowsHalfDays: $type->allows_half_days,
            eligibleGender: $type->eligible_gender,
            requiresAttachment: $type->requires_attachment,
            sortOrder: $type->sort_order,
            isActive: $type->is_active,
        );
    }
}
