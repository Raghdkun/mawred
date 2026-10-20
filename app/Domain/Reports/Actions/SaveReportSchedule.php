<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\ReportScheduleInputData;
use App\Domain\Reports\Enums\ScheduleFrequency;
use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Reports\Support\ScheduleClock;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Sets up (or changes) a schedule for a report its owner may export (D120). Filters are checked
 * now; dates are kept only as a relative range, so every run covers its own period. The first
 * run is the next matching hour in the owner's timezone.
 */
final readonly class SaveReportSchedule
{
    /** Schedules one person can have. */
    public const int MAX_PER_PERSON = 20;

    public function __construct(private ConnectionInterface $db, private ReportRegistry $registry, private ParamResolver $params) {}

    public function handle(Actor $actor, ReportScheduleInputData $input, ?ReportSchedule $schedule = null): ReportSchedule
    {
        $user = $actor->user;
        $report = $user instanceof User ? $this->registry->resolve($input->report, $user) : null;

        if (! $user instanceof User || ! $report instanceof Report || ! $this->registry->canExport($user, $report)) {
            throw new DomainRuleViolation('reports.forbidden', __('You can’t export this report.'), status: 403);
        }

        if (! $schedule instanceof ReportSchedule && ReportSchedule::query()->where('user_id', $user->id)->count() >= self::MAX_PER_PERSON) {
            throw new DomainRuleViolation('reports.too_many_schedules', __('You can have up to :count scheduled reports.', ['count' => self::MAX_PER_PERSON]));
        }

        $stored = array_filter(
            array_intersect_key($input->params ?? [], array_flip(['range', 'branch', 'department', 'days', 'year', 'leave_type', 'log'])),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
        $this->params->resolve($report, $stored);
        $timezone = FieldSpec::string($user->timezone ?? config('app.timezone'), 'UTC');
        $weekday = $input->frequency === ScheduleFrequency::Weekly ? ($input->weekday ?? 1) : null;
        $monthDay = $input->frequency === ScheduleFrequency::Monthly ? ($input->monthDay ?? 1) : null;

        return $this->db->transaction(function () use ($user, $input, $schedule, $stored, $timezone, $weekday, $monthDay): ReportSchedule {
            $schedule ??= new ReportSchedule(['user_id' => $user->id]);
            $schedule->fill([
                'report' => $input->report,
                'params' => $stored,
                'format' => $input->format,
                'frequency' => $input->frequency,
                'weekday' => $weekday,
                'month_day' => $monthDay,
                'hour' => $input->hour,
                'timezone' => $timezone,
                'locale' => app()->getLocale() === 'ar' ? 'ar' : 'en',
                'next_run_at' => ScheduleClock::next($input->frequency, $weekday, $monthDay, $input->hour, $timezone, CarbonImmutable::now()),
                'paused_at' => null,
                'paused_reason' => null,
            ])->save();

            return $schedule;
        });
    }
}
