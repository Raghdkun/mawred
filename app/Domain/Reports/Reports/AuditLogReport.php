<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Audit\Models\Activity;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Instants;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit log export (D36) for a period. Changes list the fields that changed, never their
 * values, so a file never carries what the screen masks.
 */
final class AuditLogReport extends BaseReport
{
    private const array LOGS = ['model', 'auth', 'access', 'setup'];

    public function key(): string
    {
        return 'admin.audit_log';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Admin;
    }

    public function title(): array
    {
        return ['en' => 'Audit log', 'ar' => 'سجل التدقيق'];
    }

    public function description(): array
    {
        return ['en' => 'Who did what and when, for a period.', 'ar' => 'من فعل ماذا ومتى خلال فترة.'];
    }

    public function permission(): string
    {
        return 'audit.export';
    }

    public function filters(): array
    {
        $labels = [
            'model' => ['en' => 'Records', 'ar' => 'السجلات'],
            'auth' => ['en' => 'Sign-in', 'ar' => 'تسجيل الدخول'],
            'access' => ['en' => 'Access', 'ar' => 'الصلاحيات'],
            'setup' => ['en' => 'Setup', 'ar' => 'الإعداد'],
        ];

        return [
            new ReportFilter('period', FilterType::Period),
            new ReportFilter('log', FilterType::Choice, label: ['en' => 'Log', 'ar' => 'السجل'], options: array_map(fn (string $log): array => ['value' => $log, 'label' => $labels[$log]], self::LOGS)),
        ];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('occurred_at', 'When', 'الوقت'),
            ReportColumn::text('log', 'Log', 'السجل'),
            ReportColumn::text('event', 'Event', 'الحدث'),
            ReportColumn::text('description', 'Description', 'الوصف'),
            ReportColumn::text('actor', 'By', 'بواسطة'),
            ReportColumn::text('impersonator', 'Impersonated by', 'بانتحال من'),
            ReportColumn::text('subject', 'Record', 'السجل المتأثر'),
            ReportColumn::text('changed', 'Changed fields', 'الحقول المعدّلة'),
            ReportColumn::text('channel', 'Channel', 'القناة'),
            ReportColumn::text('ip', 'IP address', 'عنوان IP'),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        [$start, $end] = Instants::between($params->from(), $params->to());
        $zone = FieldSpec::string(config('app.timezone'), 'UTC');
        $query = $this->scoped(Activity::query(), $user, SubjectBinding::user('causer_id'))
            ->with(['causer', 'subject', 'impersonator'])
            // Partition pruning (ADR-0018): the bounds go straight onto created_at.
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when($params->string('log'), fn ($q, string $log) => $q->where('log_name', $log))
            ->select('activity_log.*');

        foreach ($query->lazyById(500, 'id') as $activity) {
            $causer = $activity->causer;
            $subject = $activity->subject;
            $subjectId = $subject instanceof Model && array_key_exists('ulid', $subject->getAttributes()) ? $subject->getAttributes()['ulid'] : null;

            yield [
                'occurred_at' => $activity->created_at?->setTimezone($zone)->format('Y-m-d H:i:s') ?? '',
                'log' => (string) $activity->log_name,
                'event' => (string) $activity->event,
                'description' => $activity->description,
                'actor' => $causer instanceof User ? $causer->name : '',
                'impersonator' => $activity->impersonator instanceof User ? $activity->impersonator->name : '',
                'subject' => $activity->subject_type === null ? '' : class_basename($activity->subject_type).(is_string($subjectId) ? " {$subjectId}" : ''),
                'changed' => implode(', ', array_map(strval(...), array_keys((array) ($activity->attribute_changes?->get('attributes') ?? [])))),
                'channel' => (string) $activity->channel,
                'ip' => (string) $activity->ip,
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $byLog = [];

        foreach ($rows as $row) {
            $byLog[(string) $row['log']] = ($byLog[(string) $row['log']] ?? 0) + 1;
        }

        return new ReportSummary(
            [
                ['label' => $ar ? 'الأحداث' : 'Events', 'value' => count($rows), 'format' => 'number'],
                ['label' => $ar ? 'المستخدمون' : 'People', 'value' => count(array_unique(array_filter(array_column($rows, 'actor')))), 'format' => 'number'],
            ],
            $byLog === [] ? null : ['type' => 'bar', 'x' => 'log', 'series' => [['key' => 'count', 'label' => $ar ? 'الأحداث' : 'Events']],
                'data' => array_map(fn (string $log, int $n): array => ['log' => $log, 'count' => $n], array_keys($byLog), array_values($byLog))],
        );
    }
}
