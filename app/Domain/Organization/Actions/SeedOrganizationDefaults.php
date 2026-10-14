<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\EmploymentType;

/**
 * Production defaults (brief §21): common employment types HR can rename, extend or archive.
 * Idempotent: existing codes are left as HR edited them.
 */
final readonly class SeedOrganizationDefaults
{
    /** @var array<string, array{en: string, ar: string}> */
    public const array EMPLOYMENT_TYPES = [
        'FT' => ['en' => 'Full-time', 'ar' => 'دوام كامل'],
        'PT' => ['en' => 'Part-time', 'ar' => 'دوام جزئي'],
        'CT' => ['en' => 'Contract', 'ar' => 'عقد مؤقت'],
        'IN' => ['en' => 'Intern', 'ar' => 'متدرب'],
    ];

    public function handle(): void
    {
        foreach (self::EMPLOYMENT_TYPES as $code => $name) {
            if (! EmploymentType::withTrashed()->where('code', $code)->exists()) {
                EmploymentType::query()->create(['code' => $code, 'name' => $name]);
            }
        }
    }
}
