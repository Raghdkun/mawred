<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Support\Pdf\Gotenberg;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Offer letter PDFs (D95): the draft for review, and the signed letter once accepted (kept on the
 * private disk). Printing can fail without undoing an acceptance; the next download prints again.
 */
final readonly class SignedOfferPdf
{
    public function __construct(private OfferLetter $letters, private Gotenberg $pdf) {}

    public function draft(Offer $offer): string
    {
        return $this->pdf->html($this->letters->html($offer, $this->candidate($offer)));
    }

    public function signed(Offer $offer): ?string
    {
        if ($offer->status !== OfferStatus::Accepted || $offer->signature_path === null) {
            return null;
        }

        $disk = Storage::disk(Offer::DISK);

        if ($offer->signed_pdf_path !== null && $disk->exists($offer->signed_pdf_path)) {
            return $disk->get($offer->signed_pdf_path);
        }

        try {
            $pdf = $this->pdf->html($this->letters->html($offer, $this->candidate($offer), [
                'name' => (string) $offer->signed_name,
                'at' => $offer->responded_at ?? CarbonImmutable::now(),
                'image' => (string) $disk->get($offer->signature_path),
            ]));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $path = "offers/{$offer->ulid}/signed.pdf";
        $disk->put($path, $pdf);
        $offer->forceFill(['signed_pdf_path' => $path])->save();

        return $pdf;
    }

    private function candidate(Offer $offer): Candidate
    {
        /** @var JobApplication $application */
        $application = $offer->application()->with('candidate')->firstOrFail();

        return $application->candidate ?? new Candidate;
    }
}
