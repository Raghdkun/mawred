<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Enums\PolicySection;

final class GraceKey extends PolicyKey
{
    public function id(): string
    {
        return 'attendance.grace';
    }

    public function section(): PolicySection
    {
        return PolicySection::Attendance;
    }

    public function valueClass(): string
    {
        return GraceValue::class;
    }

    public function default(): GraceValue
    {
        return new GraceValue(lateMinutes: 15, earlyLeaveMinutes: 0);
    }

    public function affects(): array
    {
        return ['attendance'];
    }
}
