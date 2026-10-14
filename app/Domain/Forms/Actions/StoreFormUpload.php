<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Enums\FieldType;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormSchema;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Accepts a file for one field of a form while it is being filled in (ADR-0008): the content
 * type is sniffed (not trusted from the browser), size and kind follow the field, and clamd
 * scans it when configured. It stays private and expires in 24 hours unless a submission
 * claims it.
 */
final readonly class StoreFormUpload
{
    public function __construct(private FileScanner $scanner) {}

    public function handle(Form $form, FormVersion $version, string $fieldKey, UploadedFile $file, ?User $user, ?string $sessionHash): FormUpload
    {
        $field = FormSchema::fieldsByKey($version->schema)[$fieldKey] ?? null;

        if (! is_array($field) || ($field['type'] ?? null) !== FieldType::File->value) {
            throw new DomainRuleViolation('forms.not_a_file_field', __('This field does not take files.'), ['file' => [__('This field does not take files.')]]);
        }

        $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $kinds = is_array($validation['accept'] ?? null) ? $validation['accept'] : array_keys(FormSchema::FILE_KINDS);
        $allowed = array_merge(...array_map(fn (mixed $kind): array => is_string($kind) ? (FormSchema::FILE_KINDS[$kind] ?? []) : [], $kinds));
        $maxKb = is_int($validation['maxSizeKb'] ?? null) ? $validation['maxSizeKb'] : 10240;
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowed, true)) {
            throw new DomainRuleViolation('forms.file_type', __('This type of file is not accepted here.'), ['file' => [__('This type of file is not accepted here.')]]);
        }

        if ($file->getSize() > $maxKb * 1024) {
            throw new DomainRuleViolation('forms.file_size', __('The file is larger than :size KB.', ['size' => $maxKb]), ['file' => [__('The file is larger than :size KB.', ['size' => $maxKb])]]);
        }

        if (! $this->scanner->isClean($file->getRealPath() ?: $file->path())) {
            throw new DomainRuleViolation('forms.file_infected', __('This file was rejected by the virus scanner.'), ['file' => [__('This file was rejected by the virus scanner.')]]);
        }

        $ulid = (string) Str::ulid();
        $path = $file->storeAs("form-uploads/{$form->ulid}", strtolower($ulid), FormUpload::DISK);

        $upload = (new FormUpload)->forceFill([
            'ulid' => strtolower($ulid),
            'form_id' => $form->id,
            'field_key' => $fieldKey,
            'path' => (string) $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'user_id' => $user?->id,
            'session_hash' => $user instanceof User ? null : $sessionHash,
            'expires_at' => now()->addHours(FormUpload::TTL_HOURS),
        ]);
        $upload->save();

        return $upload;
    }
}
