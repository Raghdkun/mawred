<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\CourseModule;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Files\FileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Puts the file on a file module (D98), scanned like every upload; a new file replaces the old. */
final readonly class AttachModuleFile
{
    public function __construct(private FileScanner $scanner) {}

    public function handle(Actor $actor, CourseModule $module, UploadedFile $file): CourseModule
    {
        if ($module->kind !== ModuleKind::File) {
            throw new DomainRuleViolation('training.not_a_file_module', __('Only file modules take a file.'));
        }

        if (! $this->scanner->isClean($file->getRealPath() ?: '')) {
            throw new DomainRuleViolation('training.file_infected', __('This file was flagged by the virus scanner and was not saved.'), ['file' => [__('This file was flagged by the virus scanner and was not saved.')]]);
        }

        $name = mb_substr($file->getClientOriginalName(), 0, 200);
        $path = $file->storeAs("training/modules/{$module->ulid}", $module->ulid.'.'.($file->guessExtension() ?? 'bin'), CourseModule::DISK);

        if ($module->file_path !== null && $module->file_path !== $path) {
            Storage::disk(CourseModule::DISK)->delete($module->file_path);
        }

        $module->forceFill(['file_path' => $path ?: null, 'file_name' => $name])->save();

        activity('training')->causedBy($actor->user)->performedOn($module)->event('file')->withProperties(['name' => $name])->log('Module file attached');

        return $module;
    }
}
