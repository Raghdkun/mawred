<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Certificate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A certificate on someone's record (D103, D104): course, dates, state and where to check it. */
#[TypeScript]
final class CertificateData extends Data
{
    public function __construct(
        public string $id,
        public string $serial,
        public string $course,
        public string $holder,
        public string $issuedOn,
        public ?string $expiresOn,
        public string $state,
        public string $verifyUrl,
        public ?string $pdfUrl,
    ) {}

    public static function fromCertificate(Certificate $certificate, string $locale, string $today): self
    {
        $state = $certificate->state($today);

        return new self(
            $certificate->ulid,
            $certificate->serial,
            FieldSpec::text($certificate->getTranslations('title'), $locale),
            FieldSpec::text($certificate->getTranslations('holder'), $locale),
            $certificate->issued_on->toDateString(),
            $certificate->expires_on?->toDateString(),
            $state,
            route('certificates.verify', $certificate->serial),
            $state === 'revoked' ? null : route('training.certificates.pdf', $certificate),
        );
    }
}
