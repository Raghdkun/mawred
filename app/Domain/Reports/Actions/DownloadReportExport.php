<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\ReportRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

/**
 * Hands an export to the person who made it (D114) while it's kept and they may still export the
 * report; anyone else gets a 404. Every download is audited.
 */
final readonly class DownloadReportExport
{
    public function __construct(private ReportRegistry $registry) {}

    /**
     * @return array{path: string, name: string}
     */
    public function handle(User $user, ReportRun $run): array
    {
        $report = $this->registry->resolve($run->report, $user);
        $disk = Storage::disk(ReportRun::DISK);

        if ($run->user_id !== $user->id
            || $run->status !== RunStatus::Ready
            || $run->file_path === null
            || ($run->expires_at !== null && $run->expires_at->isBefore(CarbonImmutable::now()))
            || ! $report instanceof Report
            || ! $this->registry->canExport($user, $report)
            || ! $disk->exists($run->file_path)) {
            throw (new ModelNotFoundException)->setModel(ReportRun::class, [$run->ulid]);
        }

        activity('reports')->causedBy($user)->performedOn($run)->event('downloaded')
            ->withProperties(['report' => $run->report, 'format' => $run->format->value])
            ->log('Report export downloaded');

        return ['path' => $disk->path($run->file_path), 'name' => (string) $run->file_name];
    }
}
