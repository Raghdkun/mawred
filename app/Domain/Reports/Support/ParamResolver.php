<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\PeriodRange;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * Turns the viewer's filter bar (or a saved run) into a report's parameters: checks each value
 * and fills defaults (this month so far, last month for monthly reports, the next 60 days).
 */
final readonly class ParamResolver
{
    public function __construct(private LocalClock $clock) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolve(Report $report, array $input): ReportParams
    {
        $today = CarbonImmutable::parse($this->clock->today(FieldSpec::string(config('app.timezone'), 'UTC')));
        $values = [];
        $errors = [];
        $string = fn (string $key): ?string => is_string($input[$key] ?? null) && $input[$key] !== '' ? $input[$key] : null;
        $date = fn (?string $value): bool => $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;

        foreach ($report->filters() as $filter) {
            switch ($filter->type) {
                case FilterType::Period:
                    $range = PeriodRange::tryFrom((string) $string('range'));

                    // A saved report has no period unless one is chosen; prebuilt ones start at this month so far.
                    if (! $range instanceof PeriodRange && $string('from') === null && $string('to') === null && $filter->default === 'any') {
                        break;
                    }

                    [$from, $to] = $range instanceof PeriodRange
                        ? $range->between($today)
                        : [$string('from') ?? $today->startOfMonth()->toDateString(), $string('to') ?? $today->toDateString()];

                    if (! $date($from) || ! $date($to) || $from > $to) {
                        $errors['from'] = [__('Choose a start date on or before the end date.')];
                    } elseif (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 731) {
                        $errors['from'] = [__('Choose a period of two years or less.')];
                    }

                    $values['from'] = $from;
                    $values['to'] = $to;
                    $values['range'] = $range?->value;
                    break;
                case FilterType::Month:
                    $month = $string('month') ?? $today->subMonthNoOverflow()->format('Y-m');

                    if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
                        $errors['month'] = [__('Choose a month.')];
                    }

                    $values['month'] = $month;
                    break;
                case FilterType::Year:
                    $year = is_numeric($input['year'] ?? null) ? (int) $input['year'] : $today->year;
                    $values['year'] = max(2000, min(2100, $year));
                    break;
                case FilterType::AsOf:
                    $asOf = $string('as_of') ?? $today->toDateString();

                    if (! $date($asOf)) {
                        $errors['as_of'] = [__('Choose a date.')];
                    }

                    $values['as_of'] = $asOf;
                    break;
                case FilterType::Days:
                    $values['days'] = is_numeric($input['days'] ?? null) ? max(1, min(365, (int) $input['days'])) : (is_int($filter->default) ? $filter->default : 60);
                    break;
                case FilterType::Branch:
                    $branch = $string('branch');

                    if ($branch !== null && ! Branch::query()->where('ulid', $branch)->exists()) {
                        $errors['branch'] = [__('Choose a branch.')];
                    } elseif ($branch === null && $filter->required) {
                        $errors['branch'] = [__('Choose a branch.')];
                    }

                    $values['branch'] = $branch;
                    break;
                case FilterType::Department:
                    $department = $string('department');

                    if ($department !== null && ! Department::query()->where('ulid', $department)->exists()) {
                        $errors['department'] = [__('Choose a department.')];
                    }

                    $values['department'] = $department;
                    break;
                case FilterType::Choice:
                    $choice = $string($filter->key) ?? (is_string($filter->default) ? $filter->default : null);

                    if ($choice !== null && ! in_array($choice, array_column($filter->options, 'value'), true)) {
                        $errors[$filter->key] = [__('Choose one of the options.')];
                    }

                    $values[$filter->key] = $choice;
                    break;
            }
        }

        // Every run remembers the day it was asked for, so a queued file matches the screen.
        $values['today'] = $today->toDateString();

        if ($errors !== []) {
            throw new DomainRuleViolation('reports.filters', (string) reset($errors)[0], $errors);
        }

        return new ReportParams($values);
    }
}
