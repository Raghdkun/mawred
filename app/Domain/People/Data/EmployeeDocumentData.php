<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\EmployeeDocument;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeDocumentData extends Data
{
    public function __construct(
        public string $id,
        public ?RefData $type,
        public ?string $number,
        public ?string $issuedOn,
        public ?string $expiresOn,
        public ?string $notes,
        public ?string $fileName,
        public ?string $mimeType,
        public ?int $size,
        public string $uploadedAt,
    ) {}

    public static function fromDocument(EmployeeDocument $document): self
    {
        $file = $document->getFirstMedia('file');
        $type = $document->type;
        /** @var array<string, string> $typeName */
        $typeName = $type?->getTranslations('name') ?? [];

        return new self(
            id: $document->ulid,
            type: $type === null ? null : new RefData($type->ulid, $type->code, $typeName),
            number: $document->number,
            issuedOn: $document->issued_on?->toDateString(),
            expiresOn: $document->expires_on?->toDateString(),
            notes: $document->notes,
            fileName: $file?->file_name,
            mimeType: $file?->mime_type,
            size: $file?->size,
            uploadedAt: $document->created_at?->toIso8601String() ?? '',
        );
    }
}
