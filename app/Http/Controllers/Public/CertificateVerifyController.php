<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Support\CertificateSerials;
use App\Domain\Training\Support\LearnerClock;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public certificate check (D104): anyone with the serial (or the QR) sees the holder's
 * name, the course, the dates and whether it's valid, expired or revoked. Nothing else.
 */
final class CertificateVerifyController extends Controller
{
    public function __invoke(string $serial, LearnerClock $clock): Response
    {
        $certificate = Certificate::query()->with('employee')->where('serial', CertificateSerials::normalise($serial))->first();
        $locale = app()->getLocale();

        return Inertia::render('public/certificate', [
            'serial' => CertificateSerials::normalise($serial),
            'certificate' => $certificate instanceof Certificate ? [
                'holder' => FieldSpec::text($certificate->getTranslations('holder'), $locale),
                'course' => FieldSpec::text($certificate->getTranslations('title'), $locale),
                'issuedOn' => $certificate->issued_on->toDateString(),
                'expiresOn' => $certificate->expires_on?->toDateString(),
                'state' => $certificate->state($clock->today($certificate->employee)),
            ] : null,
        ]);
    }
}
