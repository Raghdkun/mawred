<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\Offer;
use App\Support\Crypto\SignedTokens;

/**
 * Signed links a candidate opens without an account, on the careers site (D87): picking an
 * interview time (D89), reading and signing an offer (D83), and deleting their details (D96).
 */
final class CandidateLinks
{
    public const string BOOKING = 'interview-booking';

    public const string OFFER = 'offer';

    public const string PRIVACY = 'candidate-privacy';

    public static function booking(Interview $interview): string
    {
        return route('careers.interviews.show', SignedTokens::make(self::BOOKING, $interview->ulid));
    }

    public static function offer(Offer $offer): string
    {
        return route('careers.offers.show', SignedTokens::make(self::OFFER, $offer->ulid));
    }

    public static function privacy(Candidate $candidate): string
    {
        return route('careers.privacy.show', SignedTokens::make(self::PRIVACY, $candidate->ulid));
    }
}
