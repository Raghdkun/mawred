<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\RespondToEvent;
use App\Domain\Calendar\Enums\Response as Reply;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\Availability;
use App\Domain\Calendar\Support\MyCalendar;
use App\Domain\Calendar\Support\SlotFinder;
use App\Domain\Calendar\Support\ViewerZone;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The calendar over /api/v1: my events in a range, people to invite, free/busy with suggested
 * times (handoff 27, D76) and replies (D75).
 */
final class CalendarController extends Controller
{
    public function events(Request $request, MyCalendar $calendar): JsonResponse
    {
        $user = $this->user($request);
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        abort_if(CarbonImmutable::parse($request->string('from')->toString())->diffInDays(CarbonImmutable::parse($request->string('to')->toString())) > 62, 422, __('Choose at most two months.'));
        $data = $calendar->for($user, $request->string('from')->toString(), $request->string('to')->toString(), ViewerZone::of($user));

        return response()->json(['data' => $data['events'], 'meta' => ['shifts' => $data['shifts'], 'time_away' => $data['timeAway'], 'holidays' => $data['holidays']]]);
    }

    public function people(Request $request): JsonResponse
    {
        $this->user($request);
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'cursor' => ['nullable', 'string']]);
        $search = trim($request->string('search')->toString());

        $page = Employee::query()
            ->with(['currentPosition', 'user'])
            ->where('employees.status', '!=', EmployeeStatus::Terminated->value)
            ->whereNotNull('employees.user_id')
            ->whereHas('user', fn (Builder $q) => $q->where('status', 'active'))
            ->when($search !== '', fn ($q) => $q->whereRaw('employees.search_text ilike ?', ['%'.addcslashes(mb_strtolower($search), '%_\\').'%']))
            ->orderBy('employees.name_en')
            ->orderBy('employees.id')
            ->cursorPaginate(20);
        $people = [];

        foreach ($page->items() as $employee) {
            if ($employee->user instanceof User) {
                $people[] = ['id' => $employee->user->ulid, 'name' => $employee->name_en, 'name_ar' => $employee->name_ar, 'position' => $employee->currentPosition?->getTranslations('title')];
            }
        }

        return response()->json([
            'data' => $people,
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'prev_cursor' => $page->previousCursor()?->encode(), 'per_page' => 20],
        ]);
    }

    public function availability(Request $request, Availability $availability): JsonResponse
    {
        $viewer = $this->user($request);
        Gate::authorize('calendar.create');
        $request->validate([
            'people' => ['required', 'array', 'min:1', 'max:30'],
            'people.*' => ['string', 'size:26'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'duration' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
        ]);
        $from = CarbonImmutable::parse($request->string('from')->toString())->utc();
        $to = CarbonImmutable::parse($request->string('to')->toString())->utc();
        abort_if($from->diffInDays($to) > 14, 422, __('Choose at most two weeks.'));

        $people = User::query()->whereIn('ulid', $request->array('people'))->get()->all();
        $rows = $availability->for($viewer, array_values($people), $from, $to);
        $slots = SlotFinder::find(array_map(fn (array $r): array => $r['free'], $rows), $request->integer('duration') * 60, 1800, 12, CarbonImmutable::now('UTC')->getTimestamp());
        $iso = fn (int $ts): string => CarbonImmutable::createFromTimestampUTC($ts)->toIso8601String();

        return response()->json([
            'data' => array_map(fn (array $r): array => [
                'user_id' => $r['userId'],
                'name' => collect($people)->firstWhere('ulid', $r['userId'])->name ?? '',
                'working' => array_map(fn (array $w): array => ['start' => $iso($w[0]), 'end' => $iso($w[1])], $r['working']),
                'busy' => array_map(fn (array $b): array => ['start' => $iso($b['start']), 'end' => $iso($b['end']), 'kind' => $b['kind'], 'title' => $b['title']], $r['busy']),
            ], $rows),
            'meta' => ['suggestions' => array_map(fn (array $s): array => ['start' => $iso($s[0]), 'end' => $iso($s[1])], $slots)],
        ]);
    }

    public function respond(Request $request, CalendarEvent $event, RespondToEvent $respond): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(Gate::allows('view', $event), 404);
        $request->validate(['response' => ['required', Rule::in(['accepted', 'tentative', 'declined'])]]);
        $participant = EventParticipant::query()->where('calendar_event_id', $event->series_id ?? $event->id)->where('user_id', $user->id)->first()
            ?? EventParticipant::query()->where('calendar_event_id', $event->id)->where('user_id', $user->id)->firstOrFail();
        $respond->handle($participant, Reply::from($request->string('response')->toString()));

        return response()->json(['data' => ['response' => $participant->response->value]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
