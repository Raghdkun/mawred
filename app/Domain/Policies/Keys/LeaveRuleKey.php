<?php

declare(strict_types=1);

namespace App\Domain\Policies\Keys;

use App\Domain\Policies\Data\Values\LeaveRuleValue;
use App\Domain\Policies\Enums\PolicySection;
use App\Domain\Policies\Models\LeaveType;

/**
 * Rules for one leave type, as `leave.type.<CODE>` (a key family: one key per catalogued type, D48).
 */
final class LeaveRuleKey extends PolicyKey
{
    public const string PREFIX = 'leave.type.';

    /** Neutral defaults per common type code (D49); other types start at zero. */
    private const array DEFAULTS = [
        'ANNUAL' => ['entitlement' => 21, 'accrual' => 'monthly', 'carryOverMax' => 5, 'carryOverExpiresMonths' => 3, 'eligibleAfterDays' => 0, 'tenureSteps' => [['afterYears' => 5, 'entitlement' => 30]]],
        'SICK' => ['entitlement' => 30, 'accrual' => 'yearly_upfront', 'proRata' => 'none', 'carryOverMax' => 0],
        'UNPAID' => ['entitlement' => 0, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0],
        'MATERNITY' => ['entitlement' => 70, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0],
        'PATERNITY' => ['entitlement' => 3, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0],
        'BEREAVEMENT' => ['entitlement' => 5, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0],
        'MARRIAGE' => ['entitlement' => 5, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0],
    ];

    public function __construct(public readonly LeaveType $type) {}

    public function id(): string
    {
        return self::PREFIX.$this->type->code;
    }

    public function section(): PolicySection
    {
        return PolicySection::Leave;
    }

    public function valueClass(): string
    {
        return LeaveRuleValue::class;
    }

    public function default(): LeaveRuleValue
    {
        return LeaveRuleValue::from(self::DEFAULTS[$this->type->code] ?? ['entitlement' => 0, 'accrual' => 'none', 'proRata' => 'none', 'carryOverMax' => 0]);
    }

    public function affects(): array
    {
        return ['leave'];
    }
}
