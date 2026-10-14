<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\ProbationValue;
use App\Domain\Policies\Enums\PolicySection;

final class ProbationKey extends PolicyKey
{
    public function id(): string
    {
        return 'employment.probation';
    }

    public function section(): PolicySection
    {
        return PolicySection::Employment;
    }

    public function valueClass(): string
    {
        return ProbationValue::class;
    }

    public function default(): ProbationValue
    {
        return new ProbationValue;
    }

    public function affects(): array
    {
        return ['people'];
    }
}
