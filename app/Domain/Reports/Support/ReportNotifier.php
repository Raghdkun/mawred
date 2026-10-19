<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\Reports\Models\ReportRun;

/**
 * Tells the person who asked that their export is ready, with a link to it in Mawred; the file
 * itself never travels in a message (D114).
 */
final readonly class ReportNotifier
{
    public function __construct(private NotificationRouter $router) {}

    public function ready(User $user, ReportRun $run, string $title): void
    {
        $this->router->send($user, new NotificationMessage(
            event: 'reports.export_ready',
            category: NotificationCategory::Reports,
            content: fn (string $channel, string $locale): array => [
                'subject' => FieldSpec::string(__('Your report is ready: :report', ['report' => $title], $locale)),
                'body' => FieldSpec::string(__('Download it in Mawred within :days days.', ['days' => ReportRun::KEEP_DAYS], $locale)),
            ],
            url: route('reports.exports.download', $run),
            sourceKey: $run->ulid.':ready',
        ));
    }

    public function failed(User $user, ReportRun $run, string $title): void
    {
        $this->router->send($user, new NotificationMessage(
            event: 'reports.export_failed',
            category: NotificationCategory::Reports,
            content: fn (string $channel, string $locale): array => [
                'subject' => FieldSpec::string(__('Your report couldn’t be made: :report', ['report' => $title], $locale)),
                'body' => (string) $run->error,
            ],
            url: route('reports.exports.index'),
            sourceKey: $run->ulid.':failed',
            channels: ['in_app'],
        ));
    }
}
