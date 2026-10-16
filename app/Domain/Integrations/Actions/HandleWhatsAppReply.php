<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\RespondToEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\ReplyTokens;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Actions\DecideTask;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Errors\DomainRuleViolation;

/**
 * Someone wrote to us on WhatsApp (ADR-0015). Every inbound message opens Meta's 24-hour
 * window for that contact. A quick-reply payload `mawred:<token>` from the assignee's verified
 * number approves on the spot; rejecting needs a comment and private request types need a
 * sign-in (D24), so those get the confirmation link back instead.
 */
final readonly class HandleWhatsAppReply
{
    public function __construct(private DecideTask $decide, private WhatsAppGateway $gateway, private RespondToEvent $respond) {}

    public function handle(IntegrationType $driver, string $from, ?string $payload): void
    {
        $contact = WhatsAppContact::query()->where('phone_e164', '+'.preg_replace('/\D+/', '', $from))->first();

        if (! $contact instanceof WhatsAppContact) {
            return;
        }

        $contact->forceFill(['last_inbound_at' => now()])->save();

        if ($payload === null || ! str_starts_with($payload, 'mawred:') || ! $contact->isVerified()) {
            return;
        }

        $token = substr($payload, 7);

        if (ReplyTokens::looksLikeOne($token)) {
            $this->meetingReply($driver, $contact, $token);

            return;
        }
        $link = strlen($token) > 128 ? null : ActionToken::query()->where('token_hash', hash('sha256', $token))->first();
        $task = $link instanceof ActionToken ? WorkflowTask::query()->find($link->workflow_task_id) : null;
        $user = User::query()->find($contact->user_id);
        $locale = $user?->locale === 'ar' ? 'ar' : 'en';

        if (! $link instanceof ActionToken || $link->used_at !== null || $link->expires_at->isPast() || ! $task instanceof WorkflowTask || $task->status !== TaskStatus::Open || $task->assignee_user_id !== $contact->user_id || ! $user instanceof User) {
            $this->reply($driver, $user, $contact, __('This approval has already been handled or has expired.', [], $locale));

            return;
        }

        $instance = $task->owner();
        $request = $instance->subject_type === EmployeeRequest::class ? EmployeeRequest::query()->find($instance->subject_id) : null;
        $confirm = route('action-links.show', $token);

        if ($link->decision !== Decision::Approved || $request?->requestType()->is_sensitive === true) {
            $this->reply($driver, $user, $contact, ($link->decision === Decision::Approved ? __('This request is private. Sign in to approve it:', [], $locale) : __('Add a reason to reject it here:', [], $locale))."\n".$confirm);

            return;
        }

        try {
            $this->decide->handle($task, $user, Decision::Approved, null, DecisionChannel::WhatsApp);
        } catch (DomainRuleViolation $e) {
            $this->reply($driver, $user, $contact, $e->getMessage());

            return;
        }

        $link->forceFill(['used_at' => now()])->save();
        $this->reply($driver, $user, $contact, __('Approved: :what. Thank you.', ['what' => $request->number ?? __('the task', [], $locale)], $locale));
    }

    private function reply(IntegrationType $driver, ?User $user, WhatsAppContact $contact, string $text): void
    {
        $this->gateway->send($driver, $user, 'whatsapp.reply', new OutgoingMessage($contact->phone_e164, $text, inServiceWindow: true));
    }

    /** A Going / Maybe / Not going button on a meeting invitation (D75), from the invitee's own number. */
    private function meetingReply(IntegrationType $driver, WhatsAppContact $contact, string $token): void
    {
        $user = User::query()->find($contact->user_id);
        $locale = $user?->locale === 'ar' ? 'ar' : 'en';
        $verified = ReplyTokens::verify($token, FieldSpec::string(config('app.key')));
        $participant = $verified === null ? null : EventParticipant::query()->where('ulid', $verified[0])->first();

        if ($verified === null || ! $participant instanceof EventParticipant || $participant->user_id !== $contact->user_id) {
            $this->reply($driver, $user, $contact, __('This invitation has changed or was cancelled.', [], $locale));

            return;
        }

        $this->respond->handle($participant, $verified[1]);
        $this->reply($driver, $user, $contact, __('Thanks, your reply is saved: :reply', ['reply' => $verified[1]->label($locale)], $locale));
    }
}
