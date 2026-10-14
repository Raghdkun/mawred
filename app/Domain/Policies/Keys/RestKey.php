<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\RestValue;
use App\Domain\Policies\Enums\PolicySection;

final class RestKey extends PolicyKey
{
    public function id(): string
    {
        return 'scheduling.rest';
    }

    public function section(): PolicySection
    {
        return PolicySection::Rest;
    }

    public function valueClass(): string
    {
        return RestValue::class;
    }

    public function default(): RestValue
    {
        return new RestValue;
    }

    public function affects(): array
    {
        return ['scheduling'];
    }
}
