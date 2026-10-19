<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Jobs\GenerateReportExportJob;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportRegistry;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Asks for a report as a file (D113): records the run and hands it to the `reports` queue, which
 * builds it as the person who asked and tells them when it's ready.
 */
final readonly class QueueReportExport
{
    /** Exports one person can have waiting at once, so nobody floods the heavy queue. */
    public const int MAX_PENDING = 5;

    public function __construct(private ConnectionInterface $db, private ReportRegistry $registry) {}

    public function handle(Actor $actor, Report $report, ReportParams $params, ReportFormat $format, string $locale): ReportRun
    {
        $user = $actor->user;

        if (! $user instanceof User || ! $this->registry->canExport($user, $report)) {
            throw new DomainRuleViolation('reports.forbidden', __('You can’t export this report.'), status: 403);
        }

        $pending = ReportRun::query()->where('user_id', $user->id)->whereIn('status', ['queued', 'running'])->count();

        if ($pending >= self::MAX_PENDING) {
            throw new DomainRuleViolation('reports.too_many', __('You already have :count exports on the way. Try again when one is ready.', ['count' => $pending]));
        }

        return $this->db->transaction(function () use ($user, $report, $params, $format, $locale, $actor): ReportRun {
            $run = ReportRun::query()->create([
                'user_id' => $user->id,
                'report' => $report->key(),
                'params' => $params->toArray(),
                'format' => $format,
                'locale' => $locale === 'ar' ? 'ar' : 'en',
            ]);

            activity('reports')->causedBy($user)->performedOn($run)->event('export_requested')
                ->withProperties(['report' => $report->key(), 'format' => $format->value, 'params' => $params->toArray(), 'impersonator' => $actor->impersonator?->ulid])
                ->log('Report export requested');

            dispatch(new GenerateReportExportJob($run->id))->afterCommit();

            return $run;
        });
    }
}
