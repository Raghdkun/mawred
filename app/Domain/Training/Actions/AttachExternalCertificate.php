<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Models\ExternalTraining;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** The certificate from an external course (D105), scanned like every upload; a new one replaces the old. */
final readonly class AttachExternalCertificate
{
    public function __construct(private FileScanner $scanner) {}

    public function handle(Actor $actor, ExternalTraining $record, UploadedFile $file): ExternalTraining
    {
        if (! $this->scanner->isClean($file->getRealPath() ?: '')) {
            $message = __('This file was flagged by the virus scanner and was not saved.');

            throw new DomainRuleViolation('training.file_infected', $message, ['file' => [$message]]);
        }

        $path = $file->storeAs("training/external/{$record->ulid}", $record->ulid.'.'.($file->guessExtension() ?? 'bin'), ExternalTraining::DISK);

        if ($record->certificate_path !== null && $record->certificate_path !== $path) {
            Storage::disk(ExternalTraining::DISK)->delete($record->certificate_path);
        }

        $record->forceFill(['certificate_path' => $path ?: null, 'certificate_name' => mb_substr($file->getClientOriginalName(), 0, 200)])->save();
        activity('training')->causedBy($actor->user)->performedOn($record)->event('certificate')->log('External certificate attached');

        return $record;
    }
}
