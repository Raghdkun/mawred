<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Data\EventInputData;
use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Enums\Visibility;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\CalendarNotifier;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates or changes a meeting (handoff 27). For a repeating series (D78) the change applies to
 * the whole series, to one occurrence (an override replacing it), or to that occurrence and the
 * ones after (the series ends the day before and a new one starts). New invitees get an
 * invitation; everyone else hears about the change; removed people hear it's off for them.
 */
final readonly class SaveEvent
{
    public function __construct(private ConnectionInterface $db, private CalendarNotifier $notifier) {}

    /**
     * @param  'all'|'this'|'following'  $scope
     */
    public function handle(Actor $actor, EventInputData $input, ?CalendarEvent $event = null, string $scope = 'all', ?CarbonImmutable $occurrence = null): CalendarEvent
    {
        $organizer = $actor->user ?? throw new DomainRuleViolation('calendar.no_user', __('Sign in to plan meetings.'));
        $starts = CarbonImmutable::parse($input->startsAt)->utc();
        $ends = CarbonImmutable::parse($input->endsAt)->utc();

        if ($event instanceof CalendarEvent && $event->organizer_user_id !== $organizer->id) {
            throw new DomainRuleViolation('calendar.not_organizer', __('Only the organiser can change this meeting.'));
        }

        if ($input->recurrence !== null && ($input->recurrence['until'] ?? null) !== null && $input->recurrence['until'] < $starts->setTimezone($input->timezone)->toDateString()) {
            throw new DomainRuleViolation('calendar.bad_until', __('The repeat must end after the first meeting.'), ['recurrence.until' => [__('The repeat must end after the first meeting.')]]);
        }

        $isSeries = $event instanceof CalendarEvent && $event->recurrence !== null && $event->series_id === null;
        $scope = $isSeries && $occurrence instanceof CarbonImmutable ? $scope : 'all';

        [$saved, $tell] = $this->db->transaction(function () use ($input, $event, $scope, $occurrence, $organizer, $starts, $ends): array {
            $fields = [
                'title' => $input->title,
                'description' => $input->description,
                'location' => $input->location,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'timezone' => $input->timezone,
                'visibility' => $input->private ? Visibility::Private : Visibility::Normal,
            ];

            if (! $event instanceof CalendarEvent) {
                $created = CalendarEvent::query()->create([...$fields, 'kind' => EventKind::Meeting, 'organizer_user_id' => $organizer->id, 'recurrence' => $input->recurrence]);

                return [$created, $this->syncPeople($created, $organizer, $input)];
            }

            if ($scope === 'this') {
                $override = CalendarEvent::query()->firstOrNew(['series_id' => $event->id, 'original_start' => $occurrence]);
                $override->fill([...$fields, 'kind' => $event->kind, 'organizer_user_id' => $organizer->id, 'recurrence' => null, 'sequence' => $override->exists ? $override->sequence + 1 : 0])->save();

                return [$override, $this->syncPeople($override, $organizer, $input, $event)];
            }

            if ($scope === 'following') {
                $rule = $event->recurrence ?? ['freq' => 'weekly'];
                $event->forceFill(['recurrence' => [...$rule, 'until' => $occurrence->setTimezone($event->timezone)->subDay()->toDateString(), 'count' => null], 'sequence' => $event->sequence + 1])->save();
                $next = CalendarEvent::query()->create([...$fields, 'kind' => $event->kind, 'organizer_user_id' => $organizer->id, 'recurrence' => $input->recurrence ?? $rule]);

                return [$next, $this->syncPeople($next, $organizer, $input, $event)];
            }

            $event->fill([...$fields, 'recurrence' => $event->series_id === null ? $input->recurrence : null])->forceFill(['sequence' => $event->sequence + 1])->save();

            return [$event, $this->syncPeople($event, $organizer, $input)];
        });

        $saved->load(['participants', 'organizer']);

        foreach ($saved->participants as $participant) {
            $what = $tell['tell'][$participant->user_id] ?? null;

            if ($what !== null) {
                $this->notifier->tell($saved, $participant, $what);
            }
        }

        foreach ($tell['removed'] as $removed) {
            $this->notifier->tell($saved, $removed, 'cancelled');
        }

        activity('calendar')->causedBy($organizer)->performedOn($saved)->event($event instanceof CalendarEvent ? 'updated' : 'created')
            ->withProperties(['scope' => $scope, 'people' => $saved->participants->count()])->log($event instanceof CalendarEvent ? 'Meeting changed' : 'Meeting created');

        return $saved;
    }

    /**
     * Makes the participant list match the input. Returns who to tell what (user id => invited or
     * updated) and the participants taken off (already deleted).
     *
     * @return array{tell: array<int, 'invited'|'updated'|null>, removed: list<EventParticipant>}
     */
    private function syncPeople(CalendarEvent $event, User $organizer, EventInputData $input, ?CalendarEvent $copyRepliesFrom = null): array
    {
        $wanted = [$organizer->ulid => ParticipantRole::Organizer];

        foreach ($input->optional as $ulid) {
            $wanted[$ulid] ??= ParticipantRole::Optional;
        }

        foreach ($input->required as $ulid) {
            $wanted[$ulid] = $ulid === $organizer->ulid ? ParticipantRole::Organizer : ParticipantRole::Required;
        }

        $users = User::query()->whereIn('ulid', array_keys($wanted))->get()->keyBy('ulid');
        $existing = EventParticipant::query()->where('calendar_event_id', $event->id)->get()->keyBy('user_id');
        $previous = $copyRepliesFrom instanceof CalendarEvent ? EventParticipant::query()->where('calendar_event_id', $copyRepliesFrom->id)->pluck('response', 'user_id') : collect();
        $tell = [];

        foreach ($wanted as $ulid => $role) {
            $user = $users->get($ulid);

            if (! $user instanceof User) {
                continue;
            }

            $row = $existing->get($user->id);

            if ($row instanceof EventParticipant) {
                $row->forceFill(['role' => $role])->save();
                $tell[$user->id] = $user->id === $organizer->id ? null : 'updated';

                continue;
            }

            $carried = $previous->get($user->id);
            EventParticipant::query()->create([
                'calendar_event_id' => $event->id,
                'user_id' => $user->id,
                'role' => $role,
                'response' => $role === ParticipantRole::Organizer ? Response::Accepted : ($carried instanceof Response ? $carried : Response::Pending),
                'responded_at' => $role === ParticipantRole::Organizer ? now() : null,
            ]);
            $tell[$user->id] = $user->id === $organizer->id ? null : ($carried instanceof Response ? 'updated' : 'invited');
        }

        $keep = $users->pluck('id')->all();
        $removed = $existing->reject(fn (EventParticipant $p): bool => in_array($p->user_id, $keep, true))->values();

        foreach ($removed as $participant) {
            $participant->delete();
        }

        return ['tell' => $tell, 'removed' => array_values($removed->all())];
    }
}
