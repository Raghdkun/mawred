<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\HourlyPermissionValue;
use App\Domain\Policies\Enums\PolicySection;

final class HourlyPermissionKey extends PolicyKey
{
    public function id(): string
    {
        return 'leave.hourly_permissions';
    }

    public function section(): PolicySection
    {
        return PolicySection::Leave;
    }

    public function valueClass(): string
    {
        return HourlyPermissionValue::class;
    }

    public function default(): HourlyPermissionValue
    {
        return new HourlyPermissionValue;
    }

    public function affects(): array
    {
        return ['leave'];
    }
}
