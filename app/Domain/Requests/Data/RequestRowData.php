<?php

declare(strict_types=1);

namespace App\Domain\Requests\Data;

use App\Domain\Requests\Models\EmployeeRequest;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A request in lists (my requests, all requests). */
#[TypeScript]
final class RequestRowData extends Data
{
    /**
     * @param  array<string, string>  $typeName
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $typeCode,
        public array $typeName,
        public ?string $typeIcon,
        public string $status,
        public string $submittedAt,
        public ?string $decidedAt,
        public string $requester,
    ) {}

    /**
     * Expects `type` and `requester` loaded.
     */
    public static function fromRequest(EmployeeRequest $request): self
    {
        $type = $request->requestType();
        /** @var array<string, string> $typeName */
        $typeName = $type->getTranslations('name');

        return new self(
            id: $request->ulid,
            number: $request->number,
            typeCode: $type->code,
            typeName: $typeName,
            typeIcon: $type->icon,
            status: $request->status->value,
            submittedAt: $request->submitted_at->toIso8601String(),
            decidedAt: $request->decided_at?->toIso8601String(),
            requester: $request->requester?->displayName(app()->getLocale()) ?? '',
        );
    }
}
