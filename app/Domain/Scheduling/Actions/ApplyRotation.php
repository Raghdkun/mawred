<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Models\RotationPattern;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Fills a date range for some people from a rotation pattern, as drafts. The cycle starts on the
 * first date; days that already have a shift are skipped. Returns how many shifts were added.
 */
final readonly class ApplyRotation
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<Employee>  $employees
     */
    public function handle(Actor $actor, Branch $branch, RotationPattern $pattern, array $employees, string $from, string $to): int
    {
        $templates = ShiftTemplate::withTrashed()->whereKey(array_filter($pattern->days))->get()->keyBy('id');
        $length = max(1, count($pattern->days));

        return $this->db->transaction(function () use ($actor, $branch, $pattern, $employees, $from, $to, $templates, $length): int {
            $added = 0;

            foreach ($employees as $employee) {
                $taken = RosterShift::query()->where('employee_id', $employee->id)->whereBetween('date', [$from, $to])->get(['date'])
                    ->map(fn (RosterShift $s): string => $s->date->toDateString())->flip();

                for ($day = CarbonImmutable::parse($from), $i = 0; $day->toDateString() <= $to; $day = $day->addDay(), $i++) {
                    $templateId = $pattern->days[$i % $length] ?? null;
                    $template = $templateId === null ? null : $templates->get($templateId);

                    if (! $template instanceof ShiftTemplate || $taken->has($day->toDateString())) {
                        continue;
                    }

                    RosterShift::query()->create([
                        'branch_id' => $branch->id,
                        'employee_id' => $employee->id,
                        'shift_template_id' => $template->id,
                        'date' => $day->toDateString(),
                        'starts_at' => $template->starts_at,
                        'ends_at' => $template->ends_at,
                        'break_minutes' => $template->break_minutes,
                        'status' => ShiftStatus::Draft,
                        'created_by' => $actor->user?->id,
                    ]);
                    $added++;
                }
            }

            return $added;
        });
    }
}
