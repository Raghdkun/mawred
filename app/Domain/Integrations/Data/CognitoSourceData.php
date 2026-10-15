<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Data;

use Spatie\LaravelData\Data;

/** The Cognito source form (handoff 41): name, target request type, hosted link and mapping. */
final class CognitoSourceData extends Data
{
    /**
     * @param  list<array{from: string, to: string}>  $fields
     * @param  array<string, string|null>  $prefill
     */
    public function __construct(
        public string $name,
        public ?string $requestTypeId,
        public ?string $formUrl,
        public array $fields,
        public string $requesterBy,
        public string $requesterPath,
        public ?string $entryPath,
        public ?string $versionPath,
        public array $prefill,
        public bool $isActive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'requestTypeId' => ['nullable', 'string', 'exists:request_types,ulid'],
            'formUrl' => ['nullable', 'url:https', 'max:500', 'regex:#^https://([a-z0-9-]+\.)*cognitoforms\.com/#i'],
            'fields' => ['present', 'array', 'max:200'],
            'fields.*.from' => ['required', 'string', 'max:200'],
            'fields.*.to' => ['required', 'string', 'max:40'],
            'requesterBy' => ['required', 'in:email,employee_number'],
            'requesterPath' => ['required', 'string', 'max:200'],
            'entryPath' => ['nullable', 'string', 'max:200'],
            'versionPath' => ['nullable', 'string', 'max:200'],
            'prefill' => ['present', 'array'],
            'prefill.email' => ['nullable', 'string', 'max:200'],
            'prefill.name' => ['nullable', 'string', 'max:200'],
            'prefill.employee_number' => ['nullable', 'string', 'max:200'],
            'isActive' => ['required', 'boolean'],
        ];
    }
}
