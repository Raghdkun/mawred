<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LeaveBlackoutInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  list<string>  $leaveTypeIds  empty = every leave type
     */
    public function __construct(
        public array $name,
        public string $startsOn,
        public string $endsOn,
        public ?string $branchId = null,
        public ?string $departmentId = null,
        public array $leaveTypeIds = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'branchId' => ['nullable', 'string', 'size:26', 'exists:branches,ulid'],
            'departmentId' => ['nullable', 'string', 'size:26', 'exists:departments,ulid'],
            'leaveTypeIds' => ['array', 'max:50'],
            'leaveTypeIds.*' => ['string', 'size:26', 'exists:leave_types,ulid'],
        ];
    }
}
