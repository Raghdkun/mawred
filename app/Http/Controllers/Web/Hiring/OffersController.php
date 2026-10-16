<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\HireCandidate;
use App\Domain\Hiring\Actions\SaveOffer;
use App\Domain\Hiring\Actions\SendOffer;
use App\Domain\Hiring\Actions\SubmitOffer;
use App\Domain\Hiring\Actions\WithdrawOffer;
use App\Domain\Hiring\Data\HireCandidateData;
use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\OfferLetter;
use App\Domain\Hiring\Support\SignedOfferPdf;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

/**
 * Offers (handoff 32, D83, D91, D95): drafting with the letter, approval, sending, the PDF, and
 * hiring once the candidate accepted (D85).
 */
final class OffersController extends Controller
{
    public function store(JobApplication $application, OfferInputData $input, SaveOffer $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($application);
        $save->handle(Actor::user($user), $application, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer drafted.')]);

        return back();
    }

    public function update(Offer $offer, OfferInputData $input, SaveOffer $save, #[CurrentUser] User $user): RedirectResponse
    {
        $application = $offer->application()->firstOrFail();
        $this->authorizeUpdate($application);
        $save->handle(Actor::user($user), $application, $input, $offer);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer saved.')]);

        return back();
    }

    /** The letter text from the template for the given terms, before the draft is saved. */
    public function letter(Request $request, JobApplication $application, OfferLetter $letters): JsonResponse
    {
        $this->authorizeUpdate($application);
        $input = OfferInputData::validateAndCreate($request->all());

        return response()->json(['letter' => $letters->preview($application->candidate()->firstOrFail(), $input)]);
    }

    public function submit(Offer $offer, SubmitOffer $submit, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($offer->application()->firstOrFail());
        $submit->handle(Actor::user($user), $offer);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer sent for approval.')]);

        return back();
    }

    public function send(Request $request, Offer $offer, SendOffer $send, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($offer->application()->firstOrFail());
        $request->validate(['channel' => ['required', Rule::enum(MessageChannel::class)]]);
        $send->handle(Actor::user($user), $offer, MessageChannel::from($request->string('channel')->toString()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer sent to the candidate.')]);

        return back();
    }

    public function withdraw(Offer $offer, WithdrawOffer $withdraw, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($offer->application()->firstOrFail());
        $withdraw->handle(Actor::user($user), $offer);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer withdrawn.')]);

        return back();
    }

    public function pdf(Offer $offer, SignedOfferPdf $pdf, #[CurrentUser] User $user): Response
    {
        abort_unless(Gate::allows('hiring.viewOffer', $offer), 404);
        activity('hiring')->causedBy($user)->performedOn($offer)->event('letter.viewed')->log('Offer letter viewed');

        try {
            $bytes = $offer->status === OfferStatus::Accepted ? ($pdf->signed($offer) ?? $pdf->draft($offer)) : $pdf->draft($offer);
        } catch (Throwable $e) {
            report($e);
            abort(503, __('The PDF service is not available right now. Try again in a minute.'));
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="offer-'.$offer->ulid.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function hire(JobApplication $application, HireCandidateData $input, HireCandidate $hire, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($application);
        abort_unless(Gate::allows('people.create'), 403);
        $employee = $hire->handle(Actor::user($user), $application, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now an employee. Onboarding has started.', ['name' => $employee->name_en])]);

        return back();
    }

    private function authorizeUpdate(JobApplication $application): void
    {
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        abort_unless(Gate::allows('hiring.updateApplication', $application), 403);
    }
}
