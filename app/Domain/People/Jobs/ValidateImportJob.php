<?php

declare(strict_types=1);

namespace App\Domain\People\Jobs;

use App\Domain\People\Actions\ValidateImport;
use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Import;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Validation re-reads the whole file and rewrites its rows, so it is safe to rerun.
 */
#[Queue('imports')]
#[Tries(1)]
#[Timeout(1800)]
#[UniqueFor(1800)]
final class ValidateImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(ValidateImport $validate): void
    {
        $import = Import::query()->with('creator')->find($this->importId);

        if ($import === null || $import->status === ImportStatus::Cancelled || $import->creator === null) {
            return;
        }

        try {
            $validate->handle($import, Actor::user($import->creator, Channel::Import));
        } catch (DomainRuleViolation $e) {
            $import->forceFill(['status' => ImportStatus::Failed, 'failure' => $e->getMessage()])->save();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Import::query()->whereKey($this->importId)->update(['status' => ImportStatus::Failed->value, 'failure' => __('Validation stopped unexpectedly. Try again.')]);
    }
}
