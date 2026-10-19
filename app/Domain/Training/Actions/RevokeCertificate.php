<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Models\Certificate;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Revokes a certificate issued in error; the verification page then shows it as revoked (D104). */
final readonly class RevokeCertificate
{
    public function handle(Actor $actor, Certificate $certificate, string $reason): Certificate
    {
        if ($certificate->revoked_at !== null) {
            throw new DomainRuleViolation('training.certificate_revoked', __('This certificate was already revoked.'));
        }

        $certificate->forceFill(['revoked_at' => now(), 'revoked_reason' => mb_substr($reason, 0, 500), 'revoked_by' => $actor->user?->id])->save();

        activity('training')->causedBy($actor->user)->performedOn($certificate)->event('revoked')->withProperties(['reason' => $reason])->log('Certificate revoked');

        return $certificate;
    }
}
