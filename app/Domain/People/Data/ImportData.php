<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\Import;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Import wizard state (screen 9), polled while validating or committing.
 */
#[TypeScript]
final class ImportData extends Data
{
    /**
     * @param  list<string|null>  $headers
     * @param  array<array-key, string>  $mapping
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $fileName,
        public array $headers,
        public array $mapping,
        public int $totalRows,
        public int $validRows,
        public int $invalidRows,
        public int $toCreate,
        public int $toUpdate,
        public int $processedRows,
        public int $createdRows,
        public int $updatedRows,
        public int $failedRows,
        public ?string $failure,
        public bool $hasErrorReport,
        public string $createdAt,
    ) {}

    public static function fromImport(Import $import): self
    {
        return new self(
            id: $import->ulid,
            status: $import->status->value,
            fileName: $import->original_name,
            headers: $import->headers,
            mapping: $import->mapping,
            totalRows: $import->total_rows,
            validRows: $import->valid_rows,
            invalidRows: $import->invalid_rows,
            toCreate: $import->to_create,
            toUpdate: $import->to_update,
            processedRows: $import->processed_rows,
            createdRows: $import->created_rows,
            updatedRows: $import->updated_rows,
            failedRows: $import->failed_rows,
            failure: $import->failure,
            hasErrorReport: $import->getFirstMedia('error_report') instanceof Media,
            createdAt: $import->created_at?->toIso8601String() ?? '',
        );
    }
}
