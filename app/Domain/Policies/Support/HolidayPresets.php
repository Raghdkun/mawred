<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

/**
 * Ready-made holiday calendars. Moon-sighted Hijri holidays cannot be computed ahead, so they are
 * imported each year as one-off dates (CSV) rather than shipped as presets.
 */
final class HolidayPresets
{
    public const string US_FEDERAL = 'us-federal';

    /**
     * @return array<string, array{code: string, country: string, name: array{en: string, ar: string}, rules: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            self::US_FEDERAL => [
                'code' => 'US_FEDERAL',
                'country' => 'US',
                'name' => ['en' => 'US federal holidays', 'ar' => 'العطلات الفيدرالية الأمريكية'],
                'rules' => [
                    self::fixed(1, 1, "New Year's Day", 'رأس السنة الميلادية'),
                    self::nth(1, 1, 3, 'Martin Luther King Jr. Day', 'يوم مارتن لوثر كينغ الابن'),
                    self::nth(2, 1, 3, "Washington's Birthday", 'يوم الرؤساء'),
                    self::nth(5, 1, -1, 'Memorial Day', 'يوم الذكرى'),
                    [...self::fixed(6, 19, 'Juneteenth National Independence Day', 'يوم جونتينث'), 'fromYear' => 2021],
                    self::fixed(7, 4, 'Independence Day', 'عيد الاستقلال'),
                    self::nth(9, 1, 1, 'Labor Day', 'عيد العمال'),
                    self::nth(10, 1, 2, 'Columbus Day', 'يوم كولومبوس'),
                    self::fixed(11, 11, 'Veterans Day', 'يوم المحاربين القدامى'),
                    self::nth(11, 4, 4, 'Thanksgiving Day', 'عيد الشكر'),
                    self::fixed(12, 25, 'Christmas Day', 'عيد الميلاد المجيد'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixed(int $month, int $day, string $en, string $ar): array
    {
        return ['name' => ['en' => $en, 'ar' => $ar], 'type' => 'fixed', 'month' => $month, 'day' => $day, 'observed' => 'nearest_weekday'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nth(int $month, int $weekday, int $nth, string $en, string $ar): array
    {
        return ['name' => ['en' => $en, 'ar' => $ar], 'type' => 'nth_weekday', 'month' => $month, 'weekday' => $weekday, 'nth' => $nth];
    }
}
