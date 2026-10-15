<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A shift as the planner sends it: from a template, or with its own times. */
#[TypeScript]
final class RosterShiftInputData extends Data
{
    public function __construct(
        public string $date,
        public ?string $employeeId = null,
        public ?string $templateId = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?int $breakMinutes = null,
        public ?string $positionId = null,
        public ?string $note = null,
        public bool $autoAssign = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'employeeId' => ['nullable', 'string', 'size:26'],
            'templateId' => ['nullable', 'required_without:startsAt', 'string', 'size:26'],
            'startsAt' => ['nullable', 'required_without:templateId', 'date_format:H:i'],
            'endsAt' => ['nullable', 'required_with:startsAt', 'date_format:H:i'],
            'breakMinutes' => ['nullable', 'integer', 'between:0,480'],
            'positionId' => ['nullable', 'string', 'size:26'],
            'note' => ['nullable', 'string', 'max:300'],
            'autoAssign' => ['boolean'],
        ];
    }
}
