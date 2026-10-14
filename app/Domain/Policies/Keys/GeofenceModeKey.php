<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\GeofenceModeValue;
use App\Domain\Policies\Enums\PolicySection;

final class GeofenceModeKey extends PolicyKey
{
    public function id(): string
    {
        return 'attendance.geofence_mode';
    }

    public function section(): PolicySection
    {
        return PolicySection::Attendance;
    }

    public function valueClass(): string
    {
        return GeofenceModeValue::class;
    }

    public function default(): GeofenceModeValue
    {
        return new GeofenceModeValue('flag');
    }

    public function affects(): array
    {
        return ['attendance'];
    }
}
