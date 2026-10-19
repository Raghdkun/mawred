<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Support\Time\LocalClock;
use Carbon\CarbonImmutable;

/**
 * The resolved filter values of one report run: dates as Y-m-d, the branch and department as
 * models. Stored on a run as plain values (`toArray`) and rebuilt from them on the queue.
 */
final readonly class ReportParams
{
    /**
     * @param  array<string, string|int|null>  $values
     */
    public function __construct(public array $values) {}

    public function from(): string
    {
        return $this->string('from') ?? $this->anchor()->startOfMonth()->toDateString();
    }

    public function to(): string
    {
        return $this->string('to') ?? $this->today();
    }

    /** Y-m */
    public function month(): string
    {
        return $this->string('month') ?? $this->anchor()->subMonthNoOverflow()->format('Y-m');
    }

    public function monthStart(): string
    {
        return $this->month().'-01';
    }

    public function monthEnd(): string
    {
        return CarbonImmutable::parse($this->monthStart())->endOfMonth()->toDateString();
    }

    public function year(): int
    {
        $year = $this->values['year'] ?? null;

        return is_numeric($year) ? (int) $year : $this->anchor()->year;
    }

    public function asOf(): string
    {
        return $this->string('as_of') ?? $this->today();
    }

    public function days(int $default = 60): int
    {
        $days = $this->values['days'] ?? null;

        return is_numeric($days) ? max(1, min(365, (int) $days)) : $default;
    }

    /** The day the run was asked for (the first day of a "next N days" window). */
    public function today(): string
    {
        return $this->string('today') ?? resolve(LocalClock::class)->today(FieldSpec::string(config('app.timezone'), 'UTC'));
    }

    /** The day the run was asked for, as a date to count from. */
    private function anchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->today());
    }

    /** The last day of a "next N days" window. */
    public function until(): string
    {
        return CarbonImmutable::parse($this->today())->addDays($this->days())->toDateString();
    }

    public function branch(): ?Branch
    {
        $ulid = $this->string('branch');

        return $ulid === null ? null : Branch::query()->where('ulid', $ulid)->first();
    }

    public function department(): ?Department
    {
        $ulid = $this->string('department');

        return $ulid === null ? null : Department::query()->where('ulid', $ulid)->first();
    }

    public function string(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** A one-line description of the dates the run covers, for file headers and the exports list. */
    public function summary(string $locale): string
    {
        $ar = $locale === 'ar';

        return match (true) {
            isset($this->values['from'], $this->values['to']) => $this->from().' – '.$this->to(),
            isset($this->values['month']) => $this->month(),
            isset($this->values['year']) => (string) $this->year(),
            isset($this->values['as_of']) => ($ar ? 'في ' : 'As of ').$this->asOf(),
            isset($this->values['days']) => $this->today().' – '.$this->until(),
            default => '',
        };
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
