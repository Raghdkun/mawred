<?php

declare(strict_types=1);

namespace App\Domain\People\Support\Imports;

use App\Domain\People\Models\CustomFieldDefinition;
use App\Support\Search\SearchText;

/**
 * The fields an employee import can fill, with the header names (EN + AR) that map onto them
 * automatically. Active custom fields join as `custom.<key>`.
 */
final class EmployeeImportFields
{
    /** @var array<string, list<string>> field => header aliases */
    public const array BUILT_IN = [
        'employee_number' => ['employee number', 'employee no', 'emp no', 'employee id', 'staff number', 'الرقم الوظيفي', 'رقم الموظف'],
        'name_en' => ['name', 'full name', 'name en', 'name english', 'english name', 'الاسم بالانجليزيه', 'الاسم الانجليزي'],
        'name_ar' => ['name ar', 'name arabic', 'arabic name', 'الاسم', 'الاسم بالعربيه', 'الاسم العربي', 'الاسم الكامل'],
        'preferred_name' => ['preferred name', 'nickname', 'known as', 'الاسم المفضل'],
        'work_email' => ['email', 'work email', 'e-mail', 'email address', 'البريد الالكتروني', 'بريد العمل'],
        'work_phone' => ['phone', 'mobile', 'work phone', 'phone number', 'mobile number', 'الجوال', 'الهاتف', 'رقم الجوال'],
        'gender' => ['gender', 'sex', 'الجنس'],
        'nationality' => ['nationality', 'country', 'الجنسيه'],
        'date_of_birth' => ['date of birth', 'dob', 'birth date', 'birthday', 'تاريخ الميلاد'],
        'national_id' => ['national id', 'id number', 'national id number', 'civil id', 'iqama', 'رقم الهويه', 'الهويه الوطنيه', 'رقم الاقامه'],
        'hire_date' => ['hire date', 'start date', 'joining date', 'date joined', 'date of joining', 'تاريخ التعيين', 'تاريخ المباشره', 'تاريخ الالتحاق'],
        'probation_ends_on' => ['probation end', 'probation ends', 'probation end date', 'نهايه التجربه', 'تاريخ انتهاء التجربه'],
        'branch' => ['branch', 'branch code', 'location', 'site', 'الفرع', 'رمز الفرع'],
        'department' => ['department', 'department code', 'dept', 'القسم', 'الاداره'],
        'position' => ['position', 'job title', 'title', 'role', 'المسمى الوظيفي', 'الوظيفه'],
        'employment_type' => ['employment type', 'contract type', 'type', 'نوع التوظيف', 'نوع العقد'],
        'grade' => ['grade', 'level', 'band', 'الدرجه', 'المرتبه'],
        'manager' => ['manager', 'line manager', 'reports to', 'manager number', 'manager email', 'المدير', 'المدير المباشر'],
    ];

    /** Column titles for templates, translated through lang/ar.json; also recognised on upload. */
    public const array LABELS = [
        'employee_number' => 'Employee number',
        'name_en' => 'Name (English)',
        'name_ar' => 'Name (Arabic)',
        'preferred_name' => 'Preferred name',
        'work_email' => 'Work email',
        'work_phone' => 'Work phone',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'date_of_birth' => 'Date of birth',
        'national_id' => 'National ID',
        'hire_date' => 'Hire date',
        'probation_ends_on' => 'Probation ends on',
        'branch' => 'Branch',
        'department' => 'Department',
        'position' => 'Position',
        'employment_type' => 'Employment type',
        'grade' => 'Grade',
        'manager' => 'Manager',
    ];

    /** Required to create someone new (updates may leave them blank). */
    public const array REQUIRED_FOR_CREATE = ['name_en', 'hire_date', 'branch'];

    /**
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        $fields = self::BUILT_IN;

        foreach (CustomFieldDefinition::query()->where('is_active', true)->orderBy('sort_order')->get() as $definition) {
            $fields["custom.{$definition->key}"] = array_values(array_filter([
                $definition->key,
                ...array_values((array) $definition->getTranslations('label')),
            ], is_string(...)));
        }

        return $fields;
    }

    /**
     * Best-guess column mapping from header names: column index => field key.
     *
     * @param  list<string|null>  $headers
     * @return array<array-key, string>
     */
    public static function suggest(array $headers): array
    {
        $aliases = [];

        foreach (self::all() as $field => $names) {
            $label = self::LABELS[$field] ?? null;
            $titles = $label === null ? [] : [$label, self::translate($label, 'ar')];

            foreach ([$field, ...$titles, ...$names] as $name) {
                $aliases[self::key($name)] ??= $field;
            }
        }

        $mapping = [];
        $taken = [];

        foreach ($headers as $index => $header) {
            $field = $header === null ? null : ($aliases[self::key($header)] ?? null);

            if ($field !== null && ! isset($taken[$field])) {
                $mapping[(string) $index] = $field;
                $taken[$field] = true;
            }
        }

        return $mapping;
    }

    /**
     * Template header row in a locale: built-in labels, then custom field labels.
     *
     * @return list<string>
     */
    public static function titles(string $locale): array
    {
        $titles = [];

        foreach (array_keys(self::all()) as $field) {
            if (isset(self::LABELS[$field])) {
                $titles[] = self::translate(self::LABELS[$field], $locale);
            } else {
                $definition = CustomFieldDefinition::query()->where('key', substr($field, 7))->first();
                $label = $definition?->getTranslation('label', $locale);
                $titles[] = is_string($label) && $label !== '' ? $label : $field;
            }
        }

        return $titles;
    }

    private static function translate(string $label, string $locale): string
    {
        $text = __($label, [], $locale);

        return is_string($text) ? $text : $label;
    }

    public static function key(string $header): string
    {
        return trim((string) preg_replace('/[\s_\-().:*]+/u', ' ', SearchText::normalize($header)));
    }
}
