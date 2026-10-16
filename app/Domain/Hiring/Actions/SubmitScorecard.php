<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\Recommendation;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Scorecard;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * A panelist's feedback (D90): ratings 1–4 per criterion with optional notes, an overall
 * recommendation and a comment. Saved as a draft until submitted; once submitted it can't change,
 * the panelist can read the others' (D82), and the application's average is updated.
 */
final readonly class SubmitScorecard
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<string, array{rating: int, note?: string|null}>  $ratings  criterion ULID => rating
     */
    public function handle(Actor $actor, Interview $interview, array $ratings, ?Recommendation $recommendation, ?string $comment, bool $submit): Scorecard
    {
        $user = $actor->user;

        if ($user === null || ! $interview->panel()->whereKey($user->id)->exists()) {
            throw new DomainRuleViolation('hiring.not_on_panel', __('Only the interview panel gives feedback on it.'));
        }

        if ($interview->status === InterviewStatus::Cancelled) {
            throw new DomainRuleViolation('hiring.interview_closed', __('This interview was cancelled.'));
        }

        /** @var JobApplication $application */
        $application = $interview->application()->firstOrFail();
        $criteria = ScorecardCriterion::query()->where('job_requisition_id', $application->job_requisition_id)->pluck('ulid')->all();
        $clean = [];

        foreach ($ratings as $ulid => $rating) {
            if (in_array($ulid, $criteria, true) && $rating['rating'] >= 1 && $rating['rating'] <= 4) {
                $note = trim((string) ($rating['note'] ?? ''));
                $clean[$ulid] = ['rating' => $rating['rating'], 'note' => $note === '' ? null : mb_substr($note, 0, 1000)];
            }
        }

        if ($submit && ($recommendation === null || count($clean) < count($criteria))) {
            $message = __('Rate every criterion and give your recommendation before submitting.');

            throw new DomainRuleViolation('hiring.scorecard_incomplete', $message, ['recommendation' => [$message]]);
        }

        return $this->db->transaction(function () use ($actor, $user, $interview, $application, $clean, $recommendation, $comment, $submit): Scorecard {
            $card = Scorecard::query()->where('interview_id', $interview->id)->where('user_id', $user->id)->lockForUpdate()->first()
                ?? new Scorecard(['interview_id' => $interview->id, 'user_id' => $user->id]);

            if ($card->submitted_at !== null) {
                throw new DomainRuleViolation('hiring.scorecard_submitted', __('You already submitted this scorecard.'));
            }

            $card->fill([
                'ratings' => $clean,
                'recommendation' => $recommendation,
                'comment' => $comment === null || trim($comment) === '' ? null : trim($comment),
                'submitted_at' => $submit ? now() : null,
            ])->save();

            if ($submit) {
                $scores = Scorecard::query()->whereNotNull('submitted_at')
                    ->whereIn('interview_id', Interview::query()->select('id')->where('job_application_id', $application->id))
                    ->get(['recommendation'])
                    ->map(fn (Scorecard $s): int => $s->recommendation instanceof Recommendation ? $s->recommendation->score() : 0)
                    ->filter();
                $application->forceFill(['score' => round((float) $scores->avg(), 2), 'last_activity_at' => now()])->save();

                ApplicationEvent::query()->create([
                    'job_application_id' => $application->id,
                    'kind' => ApplicationEventKind::Scorecard,
                    'actor_user_id' => $user->id,
                    'data' => ['interview' => $interview->ulid, 'recommendation' => $recommendation->value],
                    'created_at' => now(),
                ]);

                activity('hiring')->causedBy($actor->user)->performedOn($card)->event('submitted')->log('Scorecard submitted');
            }

            return $card;
        });
    }
}
