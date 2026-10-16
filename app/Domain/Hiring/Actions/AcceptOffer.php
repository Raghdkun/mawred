<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\OfferResponses;
use App\Domain\Hiring\Support\SignedOfferPdf;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * The candidate accepts on the offer's signed link (D83): their typed full name and drawn
 * signature, the time and a hash of their IP are stamped into the signed letter and the audit
 * log. Not a qualified e-signature.
 */
final readonly class AcceptOffer
{
    /** A drawn signature is a small PNG. */
    public const int MAX_SIGNATURE_BYTES = 300_000;

    public function __construct(private OfferResponses $responses, private SignedOfferPdf $pdf) {}

    public function handle(Offer $offer, string $name, string $signaturePng, ?string $ip): Offer
    {
        $this->responses->assertOpen($offer);
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            throw new DomainRuleViolation('hiring.signature_name', __('Type your full name.'), ['name' => [__('Type your full name.')]]);
        }

        if (strlen($signaturePng) > self::MAX_SIGNATURE_BYTES || ! str_starts_with($signaturePng, "\x89PNG\r\n\x1a\n")) {
            throw new DomainRuleViolation('hiring.signature', __('Draw your signature in the box.'), ['signature' => [__('Draw your signature in the box.')]]);
        }

        $now = CarbonImmutable::now();
        $path = "offers/{$offer->ulid}/signature.png";
        Storage::disk(Offer::DISK)->put($path, $signaturePng);

        $offer->forceFill([
            'status' => OfferStatus::Accepted,
            'responded_at' => $now,
            'signed_name' => mb_substr($name, 0, 160),
            'signature_path' => $path,
            'signed_ip_hash' => $ip === null ? null : hash_hmac('sha256', $ip, FieldSpec::string(config('app.key'))),
        ])->save();

        $this->pdf->signed($offer);
        $this->responses->record($offer, 'accepted');

        activity('hiring')->performedOn($offer)->event('accepted')
            ->withProperties(['signed_name' => $offer->signed_name, 'at' => $now->toIso8601String(), 'ip_hash' => $offer->signed_ip_hash])->log('Offer accepted');

        return $offer;
    }
}
