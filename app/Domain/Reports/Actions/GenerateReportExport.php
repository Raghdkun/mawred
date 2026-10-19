<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\ReportFile;
use App\Domain\Reports\Support\ReportNotifier;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportRegistry;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds one queued export as the person who asked: their access is checked again (it may have
 * changed since), rows come through the same scoped report the screen uses, and the file is kept
 * for a week (D114). Re-running a finished run does nothing.
 */
final readonly class GenerateReportExport
{
    public function __construct(private ReportRegistry $registry, private ReportFile $file, private ReportNotifier $notifier) {}

    public function handle(ReportRun $run): void
    {
        $claimed = ReportRun::query()->whereKey($run->id)->where('status', RunStatus::Queued->value)
            ->update(['status' => RunStatus::Running->value, 'started_at' => CarbonImmutable::now()]);

        if ($claimed === 0) {
            return;
        }

        $run->refresh();
        $user = $run->user()->first();
        $report = $this->registry->find($run->report);

        if (! $user instanceof User || ! $report instanceof Report || ! $this->registry->canExport($user, $report)) {
            $this->fail($run, __('You no longer have access to this report.', [], $run->locale), $user);

            return;
        }

        $previous = App::getLocale();
        App::setLocale($run->locale);
        $relative = 'reports/'.$run->ulid.'.'.$run->format->value;
        $disk = Storage::disk(ReportRun::DISK);
        File::ensureDirectoryExists(dirname($disk->path($relative)));

        try {
            /** @var array<string, string|int|null> $values */
            $values = $run->params;
            $params = new ReportParams($values);
            $count = $this->file->write($run->format, $disk->path($relative), $report, $report->rows($user, $params, $run->locale), $run->locale, [
                'period' => $params->summary($run->locale),
                'generated' => CarbonImmutable::now()->setTimezone($user->timezone ?? config()->string('app.timezone'))->format('Y-m-d H:i'),
            ]);
        } catch (DomainRuleViolation $e) {
            $disk->delete($relative);
            $this->fail($run, $e->getMessage(), $user);

            return;
        } catch (Throwable $e) {
            $disk->delete($relative);
            report($e);
            $this->fail($run, __('Something went wrong while making this file. Try again, or ask your administrator if it keeps happening.', [], $run->locale), $user);

            return;
        } finally {
            App::setLocale($previous);
        }

        $title = $report->title()[$run->locale === 'ar' ? 'ar' : 'en'];
        $run->forceFill([
            'status' => RunStatus::Ready,
            'rows' => $count,
            'file_path' => $relative,
            'file_name' => $this->fileName($title, $run).'.'.$run->format->value,
            'finished_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addDays(ReportRun::KEEP_DAYS),
        ])->save();

        activity('reports')->causedBy($user)->performedOn($run)->event('exported')
            ->withProperties(['report' => $run->report, 'format' => $run->format->value, 'rows' => $count])
            ->log('Report exported');

        $this->notifier->ready($user, $run, $title);
    }

    private function fail(ReportRun $run, string $message, ?User $user): void
    {
        $run->forceFill(['status' => RunStatus::Failed, 'error' => $message, 'finished_at' => CarbonImmutable::now()])->save();

        if ($user instanceof User) {
            $report = $this->registry->find($run->report);
            $this->notifier->failed($user, $run, $report instanceof Report ? $report->title()[$run->locale === 'ar' ? 'ar' : 'en'] : $run->report);
        }
    }

    /** A readable, filesystem-safe name: the report's title and the day it was made. */
    private function fileName(string $title, ReportRun $run): string
    {
        $slug = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $title), '-');

        /** @var array<string, string|int|null> $values */
        $values = $run->params;

        return ($slug === '' ? $run->report : $slug).'-'.(new ReportParams($values))->today();
    }
}
