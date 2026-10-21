<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Data\EmployeeDocumentInputData;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\UploadGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Adds or edits an employee document. A new document needs a file; editing may replace it.
 * Changing the expiry date resets the reminders already sent.
 */
final readonly class SaveEmployeeDocument
{
    public function __construct(private UploadGuard $uploads) {}

    public function handle(Actor $actor, Employee $employee, EmployeeDocumentInputData $input, ?UploadedFile $file, ?EmployeeDocument $document = null): EmployeeDocument
    {
        $type = DocumentType::query()->where('ulid', $input->documentTypeId)->where('is_active', true)->first();

        if (! $type instanceof DocumentType) {
            throw new DomainRuleViolation('people.document_type_invalid', __('This option is archived or inactive.'), ['documentTypeId' => [__('This option is archived or inactive.')]]);
        }

        if ($type->requires_expiry && $input->expiresOn === null) {
            throw new DomainRuleViolation('people.document_expiry_required', __('This document type needs an expiry date.'), ['expiresOn' => [__('This document type needs an expiry date.')]]);
        }

        if (! $document instanceof EmployeeDocument && ! $file instanceof UploadedFile) {
            throw new DomainRuleViolation('people.document_file_required', __('Attach the document file.'), ['file' => [__('Attach the document file.')]]);
        }

        if ($file instanceof UploadedFile) {
            $this->uploads->assertClean($file);
        }

        $document ??= new EmployeeDocument(['employee_id' => $employee->id, 'uploaded_by' => $actor->user?->id]);
        $expiryChanged = $document->expires_on?->toDateString() !== $input->expiresOn;

        $document->fill([
            'document_type_id' => $type->id,
            'number' => $input->number === null || trim($input->number) === '' ? null : trim($input->number),
            'issued_on' => $input->issuedOn,
            'expires_on' => $input->expiresOn,
            'notes' => $input->notes,
        ]);

        if ($expiryChanged) {
            $document->reminders_sent = [];
        }

        $document->save();

        if ($file instanceof UploadedFile) {
            $document->addMedia($file)
                ->usingFileName(Str::lower((string) Str::ulid()).'.'.$file->extension())
                ->toMediaCollection('file');
        }

        return $document;
    }
}
