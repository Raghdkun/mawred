<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Jobs;

use App\Domain\Attendance\Actions\RaiseOvertimeRequest;
use App\Domain\Attendance\Models\AttendanceDay;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/** Raises the Overtime request for a closed day once (unique per day). */
#[Queue('workflows')]
#[Tries(3)]
#[Backoff(60)]
#[UniqueFor(3600)]
final class RaiseOvertimeRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $attendanceDayId) {}

    public function uniqueId(): string
    {
        return (string) $this->attendanceDayId;
    }

    public function handle(RaiseOvertimeRequest $raise): void
    {
        $day = AttendanceDay::query()->find($this->attendanceDayId);

        if ($day instanceof AttendanceDay) {
            $raise->handle($day);
        }
    }
}
