<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\OvertimeValue;
use App\Domain\Policies\Enums\PolicySection;

final class OvertimeKey extends PolicyKey
{
    public function id(): string
    {
        return 'overtime.rules';
    }

    public function section(): PolicySection
    {
        return PolicySection::Overtime;
    }

    public function valueClass(): string
    {
        return OvertimeValue::class;
    }

    public function default(): OvertimeValue
    {
        return new OvertimeValue;
    }

    public function affects(): array
    {
        return ['attendance', 'overtime'];
    }
}
