<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Document metadata; the file itself travels separately as an upload.
 */
#[TypeScript]
final class EmployeeDocumentInputData extends Data
{
    public function __construct(
        public string $documentTypeId,
        public ?string $number = null,
        public ?string $issuedOn = null,
        public ?string $expiresOn = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'documentTypeId' => ['required', 'string', 'exists:document_types,ulid'],
            'number' => ['nullable', 'string', 'max:60'],
            'issuedOn' => ['nullable', 'date_format:Y-m-d'],
            'expiresOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issuedOn'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
