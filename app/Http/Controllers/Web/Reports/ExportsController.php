<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Actions\DownloadReportExport;
use App\Domain\Reports\Actions\QueueReportExport;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\ReportRunData;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\MyExports;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Report exports (D113, D114): asking for a file, the list of my exports while they're kept, and
 * downloading one (only mine).
 */
final class ExportsController extends Controller
{
    public function index(ReportRegistry $registry, #[CurrentUser] User $user): Response
    {
        Gate::authorize('reports.view');
        $locale = app()->getLocale();

        return Inertia::render('reports/exports', [
            'exports' => array_values(MyExports::for($user)->map(fn (ReportRun $run): ReportRunData => ReportRunData::fromRun($run, $registry->find($run->report), $locale))->all()),
            'keepDays' => ReportRun::KEEP_DAYS,
        ]);
    }

    public function store(Request $request, string $report, ReportRegistry $registry, ParamResolver $resolver, QueueReportExport $queue, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('reports.export');
        $found = $registry->find($report);
        abort_unless($found instanceof Report && $registry->canView($user, $found), 404);
        $request->validate(['format' => ['required', Rule::enum(ReportFormat::class)]]);
        /** @var array<string, mixed> $input */
        $input = $request->except('format');
        $queue->handle(Actor::user($user), $found, $resolver->resolve($found, $input), ReportFormat::from($request->string('format')->toString()), app()->getLocale());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('We’re making your file. We’ll let you know when it’s ready.')]);

        return to_route('reports.exports.index');
    }

    public function download(ReportRun $run, DownloadReportExport $download, #[CurrentUser] User $user): BinaryFileResponse
    {
        Gate::authorize('reports.view');
        $file = $download->handle($user, $run);

        return response()->download($file['path'], $file['name']);
    }
}
