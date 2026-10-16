<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Calendar\Actions\RespondToEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\ReplyTokens;
use App\Domain\Forms\Support\FieldSpec;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-tap meeting replies from email (D75): the signed token names the participant and the reply,
 * so no sign-in is needed; replying again changes the answer.
 */
final class CalendarReplyController extends Controller
{
    public function __invoke(string $token, RespondToEvent $respond): Response
    {
        $verified = ReplyTokens::verify($token, FieldSpec::string(config('app.key')));
        $participant = $verified === null ? null : EventParticipant::query()->with('event')->where('ulid', $verified[0])->first();
        abort_unless($participant instanceof EventParticipant && $participant->event !== null && $participant->event->cancelled_at === null, 404);

        $respond->handle($participant, $verified[1]);

        return Inertia::render('public/calendar-reply', [
            'title' => $participant->event->title,
            'startsAt' => $participant->event->starts_at->toIso8601String(),
            'timezone' => $participant->event->timezone,
            'response' => $verified[1]->value,
        ]);
    }
}
