<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\Texts;
use App\Support\Money\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An offer on the candidate profile (handoff 32). */
#[TypeScript]
final class OfferData extends Data
{
    /**
     * @param  array{amount_minor: int, currency: string}|null  $salary
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $jobTitle,
        public string $startOn,
        public ?array $salary,
        public ?string $branchId,
        public ?string $branch,
        public ?string $departmentId,
        public ?string $department,
        public ?string $positionId,
        public ?string $position,
        public ?string $employmentTypeId,
        public ?string $managerId,
        public ?string $manager,
        public string $locale,
        public string $letter,
        public ?string $expiresOn,
        public ?string $sentAt,
        public ?string $respondedAt,
        public ?string $signedName,
        public ?string $declineReason,
        public string $pdfUrl,
    ) {}

    public static function fromOffer(Offer $offer): self
    {
        $salary = $offer->salary;

        return new self(
            $offer->ulid,
            $offer->status->value,
            $offer->job_title,
            $offer->start_on->toDateString(),
            $salary instanceof Money ? ['amount_minor' => $salary->minor, 'currency' => $salary->currency] : null,
            $offer->branch?->ulid,
            Texts::one($offer->branch, 'name'),
            $offer->department?->ulid,
            Texts::one($offer->department, 'name'),
            $offer->position?->ulid,
            Texts::one($offer->position, 'title'),
            $offer->employmentType?->ulid,
            $offer->manager?->ulid,
            $offer->manager?->displayName(app()->getLocale()),
            $offer->locale,
            $offer->letter,
            $offer->expires_on?->toDateString(),
            $offer->sent_at?->toIso8601String(),
            $offer->responded_at?->toIso8601String(),
            $offer->signed_name,
            $offer->decline_reason,
            route('hiring.offers.pdf', $offer),
        );
    }
}
