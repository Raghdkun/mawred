<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Policies\Keys\WeekendKey;
use App\Domain\Policies\Keys\WorkHoursKey;

/**
 * Shipped presets (ADR-0010). The Neutral default (D49) is generic and labelled for review; the
 * work week stays with each branch (D46), so it is not part of the preset.
 */
final class PolicyPresets
{
    public const string FORMAT = 'mawred.policy-preset/v1';

    public const string NEUTRAL_KEY = 'neutral-default';

    public const string NEUTRAL_VERSION = '1.0.0';

    /** @var list<array{code: string, name: array{en: string, ar: string}, unit: string, isPaid: bool, allowsHalfDays: bool, eligibleGender: string|null, requiresAttachment: bool}> */
    public const array LEAVE_TYPES = [
        ['code' => 'ANNUAL', 'name' => ['en' => 'Annual leave', 'ar' => 'إجازة سنوية'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => true, 'eligibleGender' => null, 'requiresAttachment' => false],
        ['code' => 'SICK', 'name' => ['en' => 'Sick leave', 'ar' => 'إجازة مرضية'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => true, 'eligibleGender' => null, 'requiresAttachment' => true],
        ['code' => 'UNPAID', 'name' => ['en' => 'Unpaid leave', 'ar' => 'إجازة بدون راتب'], 'unit' => 'days', 'isPaid' => false, 'allowsHalfDays' => true, 'eligibleGender' => null, 'requiresAttachment' => false],
        ['code' => 'MATERNITY', 'name' => ['en' => 'Maternity leave', 'ar' => 'إجازة أمومة'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => false, 'eligibleGender' => 'female', 'requiresAttachment' => true],
        ['code' => 'PATERNITY', 'name' => ['en' => 'Paternity leave', 'ar' => 'إجازة أبوة'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => false, 'eligibleGender' => 'male', 'requiresAttachment' => true],
        ['code' => 'BEREAVEMENT', 'name' => ['en' => 'Bereavement leave', 'ar' => 'إجازة وفاة'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => false, 'eligibleGender' => null, 'requiresAttachment' => false],
        ['code' => 'MARRIAGE', 'name' => ['en' => 'Marriage leave', 'ar' => 'إجازة زواج'], 'unit' => 'days', 'isPaid' => true, 'allowsHalfDays' => false, 'eligibleGender' => null, 'requiresAttachment' => false],
    ];

    /**
     * The Neutral default values for every registered key except the work week.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function neutralValues(PolicyKeys $keys): array
    {
        $values = [];

        foreach ($keys->all() as $id => $key) {
            if (! in_array($id, [(new WeekendKey)->id(), (new WorkHoursKey)->id()], true)) {
                $values[$id] = $key->serialize($key->default());
            }
        }

        return $values;
    }
}
