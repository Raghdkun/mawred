<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Import;
use App\Domain\People\Support\Imports\EmployeeImportFields;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Spreadsheets\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Step 1 of the import wizard: keep the file, read its header row and suggest a mapping.
 */
final readonly class StartEmployeeImport
{
    public function handle(Actor $actor, UploadedFile $file): Import
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        try {
            $headers = SpreadsheetReader::header($file->getRealPath(), $extension);
        } catch (Throwable) {
            $headers = [];
        }

        if (array_filter($headers, fn (?string $h): bool => $h !== null) === []) {
            throw new DomainRuleViolation('people.import_unreadable', __('We could not read a header row in this file.'), ['file' => [__('We could not read a header row in this file.')]]);
        }

        $import = Import::query()->create([
            'type' => Import::TYPE_EMPLOYEES,
            'status' => ImportStatus::Uploaded,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'headers' => $headers,
            'mapping' => EmployeeImportFields::suggest($headers),
            'created_by' => $actor->user?->id,
        ]);

        $import->addMedia($file)->usingFileName("source-{$import->ulid}.{$extension}")->toMediaCollection('source');

        activity('import')->causedBy($actor->user)->performedOn($import)->event('uploaded')
            ->withProperties(['file' => $import->original_name])->log('Import uploaded');

        return $import;
    }
}
