<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Models\Candidate;

/**
 * Duplicate detection: the same email (normalised, matched through its blind index) is the same
 * person. A different email with a phone number already on file is probably the same person
 * too, but only a recruiter can say, so the new candidate is flagged for review.
 */
final class CandidateMatcher
{
    public static function sameEmail(?string $email): ?Candidate
    {
        $hash = Candidate::emailHash($email);

        return $hash === null ? null : Candidate::query()
            ->where('email_bidx', $hash)
            ->whereNull('merged_into_id')
            ->whereNull('anonymised_at')
            ->oldest('id')
            ->first();
    }

    public static function samePhone(?string $phone, ?int $except = null): ?Candidate
    {
        $hash = Candidate::phoneHash($phone);

        return $hash === null ? null : Candidate::query()
            ->where('phone_bidx', $hash)
            ->whereNull('merged_into_id')
            ->whereNull('anonymised_at')
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->oldest('id')
            ->first();
    }
}
