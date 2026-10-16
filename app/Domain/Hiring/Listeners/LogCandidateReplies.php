<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Listeners;

use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessageDirection;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\MessageStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Integrations\Events\WhatsAppMessageReceived;

/** A WhatsApp text from a candidate's number lands in their communications log (D94). */
final readonly class LogCandidateReplies
{
    public function handle(WhatsAppMessageReceived $event): void
    {
        $hash = Candidate::phoneHash('+'.preg_replace('/\D+/', '', $event->from));

        if ($hash === null) {
            return;
        }

        $candidate = Candidate::query()->where('phone_bidx', $hash)->whereNull('merged_into_id')->whereNull('anonymised_at')->latest('last_activity_at')->first();

        if (! $candidate instanceof Candidate) {
            return;
        }

        $application = $candidate->applications()->where('status', ApplicationStatus::Active)->latest('last_activity_at')->first();

        CandidateMessage::query()->create([
            'candidate_id' => $candidate->id,
            'job_application_id' => $application?->id,
            'direction' => MessageDirection::In,
            'channel' => MessageChannel::WhatsApp,
            'purpose' => MessagePurpose::Reply,
            'body' => mb_substr($event->text, 0, 4000),
            'status' => MessageStatus::Received,
            'created_at' => now(),
        ]);

        $candidate->forceFill(['last_activity_at' => now()])->save();
    }
}
