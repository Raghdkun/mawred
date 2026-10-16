<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\AcceptOffer;
use App\Domain\Hiring\Actions\AnonymiseCandidate;
use App\Domain\Hiring\Actions\BookInterview;
use App\Domain\Hiring\Actions\DeclineOffer;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Hiring\Support\PanelSlots;
use App\Domain\Hiring\Support\SignedOfferPdf;
use App\Domain\Hiring\Support\Texts;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Crypto\SignedTokens;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Pages a candidate opens from a signed link, without an account (D87): picking an interview
 * time (D89), reading and signing an offer (D83), and having their details deleted (D96). Each
 * page shows only what the link is for.
 */
final class CandidatePagesController extends Controller
{
    public function interview(string $token, PanelSlots $slots): Response
    {
        $interview = $this->interviewFor($token);
        $application = $this->application($interview->job_application_id);
        $open = $interview->bookingOpen(CarbonImmutable::now());

        return Inertia::render('public/interview-booking', [
            'interview' => [
                'title' => $interview->title,
                'job' => Texts::one($application->requisition, 'title') ?? '',
                'durationMinutes' => $interview->duration_minutes,
                'mode' => $interview->mode->value,
                'timezone' => $interview->timezone,
                'status' => $interview->status->value,
                'startsAt' => $interview->starts_at?->toIso8601String(),
            ],
            'slots' => $open ? array_map(fn (array $s): string => CarbonImmutable::createFromTimestampUTC($s[0])->toIso8601String(), $slots->for($interview, CarbonImmutable::now())) : [],
            'open' => $open,
            'token' => $token,
        ]);
    }

    public function book(Request $request, string $token, BookInterview $book): RedirectResponse
    {
        $interview = $this->interviewFor($token);
        abort_unless($interview->bookingOpen(CarbonImmutable::now()), 410);
        $request->validate(['start' => ['required', 'date']]);
        $organizer = User::query()->find($interview->created_by_user_id) ?? throw new DomainRuleViolation('hiring.no_organizer', __('This interview has no one to organise it.'));

        $book->handle(Actor::user($organizer), $interview, CarbonImmutable::parse($request->string('start')->toString()));

        return to_route('careers.interviews.show', $token);
    }

    public function offer(string $token): Response
    {
        $offer = $this->offerFor($token);
        $application = $this->application($offer->job_application_id);
        $offer->load(['branch', 'department', 'manager']);
        $salary = $offer->salary;
        $locale = $offer->locale;

        return Inertia::render('public/offer', [
            'offer' => [
                'status' => $offer->status === OfferStatus::Sent && $offer->expires_on !== null && $offer->expires_on->toDateString() < CarbonImmutable::now()->toDateString() ? 'expired' : $offer->status->value,
                'candidate' => $application->candidate->name ?? '',
                'jobTitle' => $offer->job_title,
                'startOn' => $offer->start_on->toDateString(),
                'salary' => $salary instanceof Money ? $salary->format($locale) : null,
                'branch' => Texts::one($offer->branch, 'name', $locale),
                'department' => Texts::one($offer->department, 'name', $locale),
                'manager' => $offer->manager?->displayName($locale),
                'expiresOn' => $offer->expires_on?->toDateString(),
                'letter' => $offer->letter,
                'locale' => $locale,
                'signedName' => $offer->signed_name,
                'respondedAt' => $offer->responded_at?->toIso8601String(),
            ],
            'pdfUrl' => route('careers.offers.pdf', $token),
            'token' => $token,
        ]);
    }

    public function offerPdf(string $token, SignedOfferPdf $pdf): HttpResponse
    {
        $offer = $this->offerFor($token);

        try {
            $bytes = $offer->status === OfferStatus::Accepted ? ($pdf->signed($offer) ?? $pdf->draft($offer)) : $pdf->draft($offer);
        } catch (Throwable $e) {
            report($e);
            abort(503, __('The PDF service is not available right now. Try again in a minute.'));
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="offer.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function accept(Request $request, string $token, AcceptOffer $accept): RedirectResponse
    {
        $offer = $this->offerFor($token);
        $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'signature' => ['required', 'string', 'max:420000', 'starts_with:data:image/png;base64,'],
            'agree' => ['accepted'],
        ]);
        $png = base64_decode(substr($request->string('signature')->toString(), strlen('data:image/png;base64,')), true);

        $accept->handle($offer, $request->string('name')->toString(), $png === false ? '' : $png, $request->ip());

        return to_route('careers.offers.show', $token);
    }

    public function decline(Request $request, string $token, DeclineOffer $decline): RedirectResponse
    {
        $offer = $this->offerFor($token);
        $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $decline->handle($offer, $request->string('reason')->toString());

        return to_route('careers.offers.show', $token);
    }

    public function privacy(string $token): Response
    {
        $candidate = $this->candidateFor($token);

        return Inertia::render('public/candidate-privacy', [
            'deleted' => $candidate->anonymised_at !== null,
            'token' => $token,
        ]);
    }

    public function forget(string $token, AnonymiseCandidate $anonymise): RedirectResponse
    {
        $anonymise->handle(Actor::system('candidate-request'), $this->candidateFor($token), 'request');

        return to_route('careers.privacy.show', $token);
    }

    private function interviewFor(string $token): Interview
    {
        $ulid = SignedTokens::verify(CandidateLinks::BOOKING, $token);
        $interview = $ulid === null ? null : Interview::query()->where('ulid', $ulid)->first();
        abort_unless($interview instanceof Interview && $interview->status !== InterviewStatus::Cancelled, 404);

        return $interview;
    }

    private function offerFor(string $token): Offer
    {
        $ulid = SignedTokens::verify(CandidateLinks::OFFER, $token);
        $offer = $ulid === null ? null : Offer::query()->where('ulid', $ulid)->first();
        abort_unless($offer instanceof Offer && in_array($offer->status, [OfferStatus::Sent, OfferStatus::Accepted, OfferStatus::Declined, OfferStatus::Expired], true), 404);

        return $offer;
    }

    private function candidateFor(string $token): Candidate
    {
        $ulid = SignedTokens::verify(CandidateLinks::PRIVACY, $token);
        $candidate = $ulid === null ? null : Candidate::query()->where('ulid', $ulid)->first();
        abort_unless($candidate instanceof Candidate, 404);

        return $candidate;
    }

    private function application(int $id): JobApplication
    {
        return JobApplication::query()->with(['candidate', 'requisition'])->findOrFail($id);
    }
}
