<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Calendar\Data\BookingPageInputData;
use App\Domain\Calendar\Models\BookingPage;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Support\ReferenceValues;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** A booking page (D77): weekly windows cut into slots that colleagues book. */
final readonly class SaveBookingPage
{
    public function handle(Actor $actor, BookingPageInputData $input, ?BookingPage $page = null): BookingPage
    {
        foreach ($input->windows as $window) {
            if ($window['to'] <= $window['from']) {
                throw new DomainRuleViolation('calendar.bad_window', __('Each window must end after it starts.'), ['windows' => [__('Each window must end after it starts.')]]);
            }
        }

        $page ??= new BookingPage(['owner_user_id' => $actor->user?->id]);
        $page->setTranslations('title', ReferenceValues::translations($input->title));
        $page->setTranslations('description', array_filter([
            'en' => $input->description['en'] ?? null,
            'ar' => $input->description['ar'] ?? null,
        ], fn (?string $v): bool => $v !== null && trim($v) !== ''));
        $page->fill([
            'location' => $input->location,
            'duration_minutes' => $input->durationMinutes,
            'buffer_minutes' => $input->bufferMinutes,
            'windows' => array_map(fn (array $w): array => ['weekday' => $w['weekday'], 'from' => $w['from'], 'to' => $w['to']], $input->windows),
            'timezone' => $input->timezone,
            'starts_on' => $input->startsOn,
            'ends_on' => $input->endsOn,
            'audience' => $input->audience,
            'branch_id' => $input->audience === 'branch' && $input->branchId !== null ? Branch::query()->where('ulid', $input->branchId)->value('id') : null,
            'department_id' => $input->audience === 'department' && $input->departmentId !== null ? Department::query()->where('ulid', $input->departmentId)->value('id') : null,
            'is_active' => true,
        ])->save();

        return $page;
    }
}
