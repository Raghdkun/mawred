<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Deterministic bilingual names for demo and performance data (no Faker, so seeds are stable).
 */
final class DemoNames
{
    /** @var list<array{0: string, 1: string, 2: string}> [en, ar, gender] */
    public const array FIRST = [
        ['Mohammed', 'محمد', 'male'], ['Ahmed', 'أحمد', 'male'], ['Abdullah', 'عبدالله', 'male'], ['Khalid', 'خالد', 'male'],
        ['Omar', 'عمر', 'male'], ['Faisal', 'فيصل', 'male'], ['Yousef', 'يوسف', 'male'], ['Saud', 'سعود', 'male'],
        ['Hamza', 'حمزة', 'male'], ['Ibrahim', 'إبراهيم', 'male'], ['Tariq', 'طارق', 'male'], ['Nasser', 'ناصر', 'male'],
        ['Ziad', 'زياد', 'male'], ['Majed', 'ماجد', 'male'], ['Rakan', 'راكان', 'male'], ['Hassan', 'حسن', 'male'],
        ['Fatimah', 'فاطمة', 'female'], ['Noura', 'نورة', 'female'], ['Sara', 'سارة', 'female'], ['Reem', 'ريم', 'female'],
        ['Lina', 'لينا', 'female'], ['Hessa', 'حصة', 'female'], ['Maha', 'مها', 'female'], ['Aisha', 'عائشة', 'female'],
        ['Layla', 'ليلى', 'female'], ['Dana', 'دانة', 'female'], ['Rana', 'رنا', 'female'], ['Mona', 'منى', 'female'],
        ['Jood', 'جود', 'female'], ['Shahad', 'شهد', 'female'], ['Amal', 'أمل', 'female'], ['Hind', 'هند', 'female'],
    ];

    /** @var list<array{0: string, 1: string}> */
    public const array FAMILY = [
        ['Al-Qahtani', 'القحطاني'], ['Al-Otaibi', 'العتيبي'], ['Al-Harbi', 'الحربي'], ['Al-Zahrani', 'الزهراني'],
        ['Al-Ghamdi', 'الغامدي'], ['Al-Dosari', 'الدوسري'], ['Al-Shehri', 'الشهري'], ['Al-Mutairi', 'المطيري'],
        ['Haddad', 'حداد'], ['Khoury', 'خوري'], ['Nasser', 'ناصر'], ['Saleh', 'صالح'],
        ['Mansour', 'منصور'], ['Barakat', 'بركات'], ['Odeh', 'عودة'], ['Hamdan', 'حمدان'],
        ['Al-Ali', 'العلي'], ['Al-Mansouri', 'المنصوري'], ['Al-Hashimi', 'الهاشمي'], ['Al-Suwaidi', 'السويدي'],
    ];

    /** @var list<array{0: string, 1: string}> English-only names for the New York branch [first, last] */
    public const array WESTERN = [
        ['Emily', 'Carter'], ['James', 'Walker'], ['Olivia', 'Bennett'], ['Daniel', 'Hughes'], ['Sophia', 'Reed'],
        ['Michael', 'Brooks'], ['Grace', 'Coleman'], ['David', 'Foster'], ['Chloe', 'Murphy'], ['Ryan', 'Price'],
    ];

    /**
     * @return array{en: string, ar: string|null, gender: string}
     */
    public static function arabic(int $index): array
    {
        $first = self::FIRST[$index % count(self::FIRST)];
        $family = self::FAMILY[intdiv($index, count(self::FIRST)) % count(self::FAMILY)];

        return ['en' => "{$first[0]} {$family[0]}", 'ar' => "{$first[1]} {$family[1]}", 'gender' => $first[2]];
    }

    /**
     * @return array{en: string, ar: string|null, gender: string|null}
     */
    public static function western(int $index): array
    {
        $first = self::WESTERN[$index % count(self::WESTERN)];
        $last = self::WESTERN[(intdiv($index, count(self::WESTERN)) + 3) % count(self::WESTERN)];

        return ['en' => "{$first[0]} {$last[1]}", 'ar' => null, 'gender' => null];
    }
}
