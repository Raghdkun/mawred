<?php

declare(strict_types=1);

namespace App\Domain\People\Jobs;

use App\Domain\People\Actions\CommitImport;
use App\Domain\People\Enums\ImportStatus;
use App\Domain\People\Models\Import;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Commits only rows still marked valid, so a rerun after a crash continues where it stopped
 * instead of creating anyone twice.
 */
#[Queue('imports')]
#[Tries(1)]
#[Timeout(3600)]
#[UniqueFor(3600)]
final class CommitImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(CommitImport $commit): void
    {
        $import = Import::query()->with('creator')->find($this->importId);

        if ($import === null || $import->status !== ImportStatus::Committing || $import->creator === null) {
            return;
        }

        $commit->handle($import, Actor::user($import->creator, Channel::Import));
    }

    public function failed(?Throwable $exception): void
    {
        Import::query()->whereKey($this->importId)->update(['status' => ImportStatus::Failed->value, 'failure' => __('The import stopped part-way. Rows already added are kept; start it again to finish.')]);
    }
}
