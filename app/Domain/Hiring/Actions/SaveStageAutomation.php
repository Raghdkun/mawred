<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\CandidateTemplates;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * What happens when a candidate enters a stage (D93): a bilingual message to the candidate, and
 * on interview stages a self-booking invite with a set panel and length.
 */
final readonly class SaveStageAutomation
{
    public function __construct(private CandidateTemplates $templates) {}

    /**
     * @param  array{message: array{enabled: bool, subject: array{en: string, ar?: string|null}, body: array{en: string, ar?: string|null}}, booking: array{enabled: bool, title: string, duration: int, panel: list<string>}}  $automation
     */
    public function handle(Actor $actor, PipelineStage $stage, array $automation): PipelineStage
    {
        $message = $automation['message'];
        $booking = $automation['booking'];

        if ($message['enabled']) {
            $subject = $this->both($message['subject']);
            $body = $this->both($message['body']);
            $this->templates->assertValid([$subject['en'], $subject['ar'], $body['en'], $body['ar']], 'message.body');
        }

        if ($booking['enabled']) {
            if ($stage->kind !== StageKind::Interview) {
                throw new DomainRuleViolation('hiring.booking_stage', __('Booking invites go out from interview stages only.'), ['booking.enabled' => [__('Booking invites go out from interview stages only.')]]);
            }

            if ($booking['panel'] === [] || User::query()->whereIn('ulid', $booking['panel'])->where('status', 'active')->count() !== count($booking['panel'])) {
                throw new DomainRuleViolation('hiring.bad_panel', __('Pick active colleagues for the panel.'), ['booking.panel' => [__('Pick active colleagues for the panel.')]]);
            }
        }

        $stage->forceFill(['automation' => [
            'message' => ['enabled' => $message['enabled'], 'subject' => $this->both($message['subject']), 'body' => $this->both($message['body'])],
            'booking' => ['enabled' => $booking['enabled'], 'title' => $booking['title'], 'duration' => $booking['duration'], 'panel' => $booking['panel']],
        ]])->save();

        activity('hiring')->causedBy($actor->user)->event('automation_changed')
            ->withProperties(['stage' => $stage->ulid, 'message' => $message['enabled'], 'booking' => $booking['enabled']])->log('Stage automation changed');

        return $stage;
    }

    /**
     * @param  array{en: string, ar?: string|null}  $text
     * @return array{en: string, ar: string}
     */
    private function both(array $text): array
    {
        return ['en' => $text['en'], 'ar' => ($text['ar'] ?? null) ?: $text['en']];
    }
}
