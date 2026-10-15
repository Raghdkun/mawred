<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\GeofenceResult;
use App\Domain\Attendance\Enums\PunchKind;
use App\Domain\Attendance\Enums\PunchSource;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Attendance\Support\AttendanceClock;
use App\Domain\Attendance\Support\Geofence;
use App\Domain\Attendance\Support\KioskCodes;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\GeofenceModeValue;
use App\Domain\Policies\Support\PolicyResolver;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;

/**
 * A check-in or check-out from the phone app or a kiosk QR (handoff 25). Offline punches carry
 * the device time they were tapped and the device time they were sent: the difference to the
 * server clock corrects them, and a clock off by more than the drift budget is flagged (D70).
 * The branch area is checked per the geofence policy (D26): `block` refuses outside punches,
 * `flag` asks for a reason and puts them on the daily board.
 */
final readonly class RecordPunch
{
    public const int DRIFT_BUDGET_SECONDS = 300;

    public const int MAX_OFFLINE_HOURS = 72;

    public const int DUPLICATE_SECONDS = 60;

    public function __construct(
        private AttendanceClock $clock,
        private EmployeeDays $days,
        private PolicyResolver $resolver,
        private RecalculateAttendanceDay $recalculate,
    ) {}

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, accuracy?: int|null}  $location
     * @param  array{tapped_at: string, sent_at: string}|null  $offline  device timestamps (ISO 8601)
     */
    public function handle(Actor $actor, Employee $employee, PunchKind $kind, array $location = [], ?string $kioskCode = null, ?array $offline = null, ?string $reason = null): AttendanceEvent
    {
        $now = CarbonImmutable::now('UTC');
        [$occurredAt, $drift] = $this->occurredAt($now, $offline);
        $flagged = $drift !== null && abs($drift) > self::DRIFT_BUDGET_SECONDS;
        $kiosk = null;

        if ($kioskCode !== null) {
            $ulid = KioskCodes::verify($kioskCode, fn (string $ulid): ?string => Kiosk::query()->where('ulid', $ulid)->where('is_active', true)->first()?->secret, $offline === null ? $now->getTimestamp() : CarbonImmutable::parse($offline['tapped_at'])->getTimestamp() - ($drift ?? 0));
            $kiosk = $ulid === null ? null : Kiosk::query()->where('ulid', $ulid)->first();

            if (! $kiosk instanceof Kiosk) {
                throw new DomainRuleViolation('attendance.bad_code', __('That code has expired. Scan the kiosk again.'), ['code' => [__('That code has expired. Scan the kiosk again.')]]);
            }
        }

        $branch = $kiosk instanceof Kiosk ? Branch::withTrashed()->find($kiosk->branch_id) : $this->days->branch($employee);
        $geofence = GeofenceResult::NotChecked;

        if (! $kiosk instanceof Kiosk) {
            $mode = $this->resolver->value($employee, $this->days->today($employee), 'attendance.geofence_mode');
            $mode = $mode instanceof GeofenceModeValue ? $mode->mode : 'flag';

            if ($mode !== 'off') {
                $geofence = Geofence::check(
                    $branch?->geofence_lat === null ? null : (float) $branch->geofence_lat,
                    $branch?->geofence_lng === null ? null : (float) $branch->geofence_lng,
                    $branch?->geofence_radius_m,
                    isset($location['latitude']) ? (float) $location['latitude'] : null,
                    isset($location['longitude']) ? (float) $location['longitude'] : null,
                    isset($location['accuracy']) ? (int) $location['accuracy'] : null,
                );

                if ($geofence !== GeofenceResult::Inside && $branch?->geofence_radius_m !== null) {
                    if ($mode === 'block') {
                        throw new DomainRuleViolation('attendance.outside', $geofence === GeofenceResult::Unknown
                            ? __('Turn on location to check in here.')
                            : __('You’re outside the branch area, so you can’t check in from here.'));
                    }

                    if ($geofence === GeofenceResult::Outside && ($reason === null || trim($reason) === '')) {
                        throw new DomainRuleViolation('attendance.reason_required', __('You’re outside the branch area. Add a reason to check in anyway.'), ['reason' => [__('You’re outside the branch area. Add a reason to check in anyway.')]]);
                    }

                    $flagged = true;
                }
            }
        }

        $date = $this->clock->dayOf($employee, $occurredAt);
        $recent = AttendanceEvent::query()
            ->where('employee_id', $employee->id)
            ->where('kind', $kind->value)
            ->whereNull('voided_at')
            ->whereBetween('occurred_at', [$occurredAt->subSeconds(self::DUPLICATE_SECONDS), $occurredAt->addSeconds(self::DUPLICATE_SECONDS)])
            ->first();

        if ($recent instanceof AttendanceEvent) {
            return $recent;
        }

        $event = AttendanceEvent::query()->create([
            'employee_id' => $employee->id,
            'branch_id' => $branch?->id,
            'kind' => $kind,
            'source' => $kiosk instanceof Kiosk ? PunchSource::Kiosk : PunchSource::Pwa,
            'occurred_at' => $occurredAt,
            'received_at' => $now,
            'local_date' => $date,
            'drift_seconds' => $drift,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'accuracy_m' => $location['accuracy'] ?? null,
            'geofence' => $geofence,
            'flagged' => $flagged,
            'reason' => $reason === null ? null : trim($reason),
            'kiosk_id' => $kiosk?->id,
            'actor_user_id' => $actor->user?->id,
        ]);

        $this->recalculate->handle($employee, $date);

        return $event;
    }

    /**
     * @param  array{tapped_at: string, sent_at: string}|null  $offline
     * @return array{0: CarbonImmutable, 1: int|null} when it happened, and the device clock's offset
     */
    private function occurredAt(CarbonImmutable $now, ?array $offline): array
    {
        if ($offline === null) {
            return [$now, null];
        }

        $tapped = CarbonImmutable::parse($offline['tapped_at'])->utc();
        $sent = CarbonImmutable::parse($offline['sent_at'])->utc();
        $drift = $sent->getTimestamp() - $now->getTimestamp();
        $occurred = $tapped->subSeconds($drift);

        if ($occurred->greaterThan($now) || $occurred->lessThan($now->subHours(self::MAX_OFFLINE_HOURS))) {
            throw new DomainRuleViolation('attendance.too_old', __('This check-in was saved too long ago to send. Ask HR for a correction.'));
        }

        return [$occurred, $drift];
    }
}
