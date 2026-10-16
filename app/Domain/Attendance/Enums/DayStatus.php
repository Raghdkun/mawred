<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/** A person's attendance day on the daily board (handoff 26). */
enum DayStatus: string
{
    case Scheduled = 'scheduled';
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case OnLeave = 'on_leave';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Not in yet'),
            self::Present => __('Present'),
            self::Late => __('Late'),
            self::Absent => __('Absent'),
            self::Incomplete => __('Missing check-out'),
            self::OnLeave => __('On leave'),
            self::Off => __('Day off'),
        };
    }
}
