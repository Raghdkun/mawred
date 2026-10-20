<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportNotifier;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Reports\Support\ScheduleClock;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;

/**
 * Starts the schedules that are due (D120): each is claimed by moving its next run forward first,
 * so two schedulers never run it twice. It runs as its owner, with their access checked now;
 * a schedule they can no longer export is paused and they're told why.
 */
final readonly class RunDueSchedules
{
    public function __construct(private ReportRegistry $registry, private ParamResolver $params, private QueueReportExport $queue, private ReportNotifier $notifier) {}

    /**
     * @return array{started: int, paused: int}
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $started = 0;
        $paused = 0;

        foreach (ReportSchedule::query()->whereNull('paused_at')->where('next_run_at', '<=', $now)->oldest('next_run_at')->limit(500)->get() as $schedule) {
            $next = ScheduleClock::next($schedule->frequency, $schedule->weekday, $schedule->month_day, $schedule->hour, $schedule->timezone, $now);
            $claimed = ReportSchedule::query()->whereKey($schedule->id)->where('next_run_at', $schedule->next_run_at)->whereNull('paused_at')->update(['next_run_at' => $next]);

            if ($claimed === 0) {
                continue;
            }

            $user = $schedule->user()->first();
            $report = $user instanceof User ? $this->registry->resolve($schedule->report, $user) : null;

            if (! $user instanceof User || ! $report instanceof Report || ! $this->registry->canExport($user, $report)) {
                $this->pause($schedule, __('You no longer have access to this report.', [], $schedule->locale), $user);
                $paused++;

                continue;
            }

            $previous = app()->getLocale();
            app()->setLocale($schedule->locale);

            try {
                $run = $this->queue->handle(Actor::user($user, Channel::System), $report, $this->params->resolve($report, $schedule->params), $schedule->format, $schedule->locale, scheduled: true);
            } catch (DomainRuleViolation $e) {
                $this->pause($schedule, $e->getMessage(), $user);
                $paused++;

                continue;
            } finally {
                app()->setLocale($previous);
            }

            $schedule->forceFill(['last_run_at' => $now, 'last_run_id' => $run->id])->save();
            $started++;
        }

        return ['started' => $started, 'paused' => $paused];
    }

    private function pause(ReportSchedule $schedule, string $reason, ?User $user): void
    {
        $schedule->forceFill(['paused_at' => CarbonImmutable::now(), 'paused_reason' => $reason])->save();

        if ($user instanceof User) {
            $this->notifier->schedulePaused($user, $schedule, $reason);
        }
    }
}
