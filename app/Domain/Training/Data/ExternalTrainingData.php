<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ExternalTraining;
use App\Support\Money\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Training taken outside the catalog (D105), with its certificate and any catalog course used instead. */
#[TypeScript]
final class ExternalTrainingData extends Data
{
    /**
     * @param  array{amountMinor: int, currency: string}|null  $cost
     * @param  array{id: string, title: string}|null  $course
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $provider,
        public ?string $startsOn,
        public ?string $endsOn,
        public ?array $cost,
        public ?string $certificateName,
        public ?string $certificateUrl,
        public ?array $course,
    ) {}

    public static function fromRecord(ExternalTraining $record, string $locale): self
    {
        $course = null;

        if ($record->enrollment_id !== null) {
            $linked = Enrollment::query()->find($record->enrollment_id)?->course;
            $course = $linked instanceof Course ? ['id' => $linked->ulid, 'title' => FieldSpec::text($linked->getTranslations('title'), $locale)] : null;
        }

        return new self(
            $record->ulid,
            $record->title,
            $record->provider,
            $record->starts_on?->toDateString(),
            $record->ends_on?->toDateString(),
            $record->cost instanceof Money ? ['amountMinor' => $record->cost->minor, 'currency' => $record->cost->currency] : null,
            $record->certificate_name,
            $record->certificate_path === null ? null : route('training.external.certificate', $record),
            $course,
        );
    }
}
