<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Models\DocumentType;

/**
 * Production defaults (brief §21): the document types most HR teams start with. Idempotent.
 */
final readonly class SeedPeopleDefaults
{
    /** @var array<string, array{name: array{en: string, ar: string}, expires: bool}> */
    public const array DOCUMENT_TYPES = [
        'CONTRACT' => ['name' => ['en' => 'Employment contract', 'ar' => 'عقد العمل'], 'expires' => false],
        'NATIONAL_ID' => ['name' => ['en' => 'National ID', 'ar' => 'الهوية الوطنية'], 'expires' => true],
        'PASSPORT' => ['name' => ['en' => 'Passport', 'ar' => 'جواز السفر'], 'expires' => true],
        'RESIDENCY' => ['name' => ['en' => 'Residency permit', 'ar' => 'تصريح الإقامة'], 'expires' => true],
        'CERTIFICATE' => ['name' => ['en' => 'Certificate', 'ar' => 'شهادة'], 'expires' => false],
    ];

    public function handle(): void
    {
        foreach (self::DOCUMENT_TYPES as $code => $type) {
            if (! DocumentType::withTrashed()->where('code', $code)->exists()) {
                DocumentType::query()->create([
                    'code' => $code,
                    'name' => $type['name'],
                    'requires_expiry' => $type['expires'],
                    'reminder_days' => $type['expires'] ? [60, 30, 7] : [],
                ]);
            }
        }
    }
}
