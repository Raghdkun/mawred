<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\CancelInterview;
use App\Domain\Hiring\Actions\ScheduleInterview;
use App\Domain\Hiring\Actions\SubmitScorecard;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\Recommendation;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Scorecard;
use App\Domain\Hiring\Support\Texts;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interviews (D89, D90, D92): planning one from the profile, calling it off, the scorecard, and
 * "My interviews" for everyone on a panel, whatever their role.
 */
final class InterviewsController extends Controller
{
    public function mine(#[CurrentUser] User $user): Response
    {
        $interviews = Interview::query()
            ->whereHas('panel', fn (Builder $q) => $q->whereKey($user->id))
            ->whereIn('status', [InterviewStatus::AwaitingBooking, InterviewStatus::Scheduled, InterviewStatus::Completed])
            ->with(['application.candidate', 'application.requisition', 'scorecards' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByRaw('starts_at desc nulls first')
            ->limit(200)
            ->get();
        $locale = app()->getLocale();

        return Inertia::render('hiring/interviews', [
            'interviews' => array_values($interviews->map(fn (Interview $i): array => [
                'id' => $i->ulid,
                'title' => $i->title,
                'status' => $i->status->value,
                'startsAt' => $i->starts_at?->toIso8601String(),
                'endsAt' => $i->ends_at?->toIso8601String(),
                'timezone' => $i->timezone,
                'candidate' => ['id' => $i->application->candidate->ulid ?? '', 'name' => $i->application->candidate->name ?? '—'],
                'application' => $i->application->ulid ?? '',
                'job' => Texts::one($i->application?->requisition, 'title', $locale) ?? '',
                'scorecard' => ($card = $i->scorecards->first()) instanceof Scorecard
                    ? ($card->submitted_at !== null ? 'submitted' : 'draft')
                    : ($i->starts_at === null || $i->starts_at->isFuture() ? 'upcoming' : 'missing'),
            ])->all()),
        ]);
    }

    public function store(JobApplication $application, InterviewInputData $input, ScheduleInterview $schedule, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($application);
        $interview = $schedule->handle(Actor::user($user), $application, $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => $interview->status === InterviewStatus::Scheduled
            ? __('Interview booked. The panel has the invitation.')
            : __('Booking link sent to the candidate.')]);

        return back();
    }

    public function destroy(Interview $interview, CancelInterview $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($interview->application()->firstOrFail());
        $cancel->handle(Actor::user($user), $interview);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interview cancelled.')]);

        return back();
    }

    public function scorecard(Request $request, Interview $interview, SubmitScorecard $submit, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('hiring.scoreInterview', $interview), 404);
        $request->validate([
            'ratings' => ['present', 'array', 'max:10'],
            'ratings.*.rating' => ['required', 'integer', 'between:1,4'],
            'ratings.*.note' => ['nullable', 'string', 'max:1000'],
            'recommendation' => ['nullable', Rule::enum(Recommendation::class)],
            'comment' => ['nullable', 'string', 'max:4000'],
            'submit' => ['required', 'boolean'],
        ]);

        /** @var array<string, array{rating: int, note?: string|null}> $ratings */
        $ratings = $request->input('ratings', []);
        $submit->handle(
            Actor::user($user),
            $interview,
            $ratings,
            $request->filled('recommendation') ? Recommendation::from($request->string('recommendation')->toString()) : null,
            $request->string('comment')->toString(),
            $request->boolean('submit'),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('submit') ? __('Scorecard submitted.') : __('Draft saved.')]);

        return back();
    }

    private function authorizeUpdate(JobApplication $application): void
    {
        abort_unless(Gate::allows('hiring.viewApplication', $application), 404);
        abort_unless(Gate::allows('hiring.updateApplication', $application), 403);
    }
}
