<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Support;

use App\Domain\Attendance\Enums\DayStatus;
use App\Domain\Policies\Support\Calculators\OvertimeResult;

/** One computed attendance day (minutes from the day's local midnight). */
final readonly class DayResult
{
    /**
     * @param  list<string>  $flags  missing_out, out_without_in, double_in
     */
    public function __construct(
        public DayStatus $status,
        public ?int $firstIn,
        public ?int $lastOut,
        public int $worked,
        public int $late,
        public int $earlyLeave,
        public OvertimeResult $overtime,
        public array $flags,
    ) {}
}
