<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Reports;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\ReportCardData;
use App\Domain\Reports\Data\ReportFilterData;
use App\Domain\Reports\Data\ReportResultData;
use App\Domain\Reports\Data\SavedReportData;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Support\CustomReport;
use App\Domain\Reports\Support\MyExports;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportPreview;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Reports\Support\SavedReportAccess;
use App\Http\Controllers\Controller;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The report library and viewer (handoff 36, D112): reports the viewer may open, grouped by
 * module, and one report with its filters, headline numbers, chart and table.
 */
final class ReportsController extends Controller
{
    public function index(ReportRegistry $registry, #[CurrentUser] User $user): Response
    {
        Gate::authorize('reports.view');
        $locale = app()->getLocale();

        return Inertia::render('reports/index', [
            'categories' => array_map(fn (ReportCategory $c): string => $c->value, ReportCategory::cases()),
            'reports' => array_map(fn (Report $r): ReportCardData => ReportCardData::fromReport($r, $locale, $registry->canExport($user, $r)), $registry->for($user)),
            'pendingExports' => MyExports::pending($user),
            'saved' => array_values(SavedReportAccess::visibleTo($user)->with('owner')->latest('updated_at')->limit(100)->get()
                ->filter(fn (SavedReport $s): bool => $registry->custom($s, $user) instanceof CustomReport)
                ->map(fn (SavedReport $s): SavedReportData => SavedReportData::fromSaved($s, $user, $locale))->all()),
            'can' => ['build' => Gate::allows('create', SavedReport::class), 'schedule' => Gate::allows('reports.export')],
        ]);
    }

    public function show(Request $request, string $report, ReportRegistry $registry, ParamResolver $resolver, #[CurrentUser] User $user): Response
    {
        Gate::authorize('reports.view');
        $found = $registry->resolve($report, $user);
        abort_unless($found instanceof Report && $registry->canView($user, $found), 404);
        $locale = app()->getLocale();
        $errors = [];

        try {
            /** @var array<string, mixed> $input */
            $input = $request->query();
            $params = $resolver->resolve($found, $input);
        } catch (DomainRuleViolation $e) {
            // A first visit with nothing chosen yet isn't an error; a bad or incomplete link is.
            $errors = $request->query() === [] ? [] : array_map(fn (array $messages): string => (string) reset($messages), $e->details);
            $params = $found->filters() === [] ? new ReportParams([]) : $this->fallback($resolver, $found);
        }

        return Inertia::render('reports/show', [
            'report' => ReportCardData::fromReport($found, $locale, $registry->canExport($user, $found)),
            'filters' => array_map(fn (ReportFilter $f): ReportFilterData => ReportFilterData::fromFilter($f, $locale), $found->filters()),
            'values' => (object) $params->toArray(),
            'selected' => [
                'branch' => $this->branch($params->branch(), $locale),
                'department' => $this->department($params->department(), $locale),
            ],
            'formats' => array_map(fn (ReportFormat $f): string => $f->value, ReportFormat::cases()),
            'filterErrors' => (object) $errors,
            'result' => $this->needsBranch($found, $params) ? null : Inertia::defer(fn (): ReportResultData => ReportPreview::build($found, $user, $params, $locale)),
            'saved' => $found instanceof CustomReport ? [
                'report' => SavedReportData::fromSaved($found->saved, $user, $locale),
                'can' => ['edit' => Gate::allows('update', $found->saved), 'share' => Gate::allows('share', $found->saved), 'delete' => Gate::allows('delete', $found->saved)],
            ] : null,
            'roles' => $found instanceof CustomReport && Gate::allows('share', $found->saved)
                ? array_values(Role::query()->where('api_only', false)->orderByDesc('rank')->get()->map(fn (Role $r): array => ['name' => $r->name, 'label' => $r->getTranslation('label', $locale)])->all())
                : [],
        ]);
    }

    /** Defaults for every filter, so a bad link still shows the report. */
    private function fallback(ParamResolver $resolver, Report $report): ReportParams
    {
        try {
            return $resolver->resolve($report, []);
        } catch (DomainRuleViolation) {
            return new ReportParams([]);
        }
    }

    /** A report that needs a branch (payroll input) shows nothing until one is chosen. */
    private function needsBranch(Report $report, ReportParams $params): bool
    {
        return array_any($report->filters(), fn ($filter): bool => $filter->key === 'branch' && $filter->required && ! $params->branch() instanceof Branch);
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function branch(?Branch $branch, string $locale): ?array
    {
        if (! $branch instanceof Branch) {
            return null;
        }

        $names = $branch->getTranslations('name');

        $label = $names[$locale] ?? $names['en'] ?? null;

        return ['id' => $branch->ulid, 'label' => is_string($label) ? $label : $branch->code];
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function department(?Department $department, string $locale): ?array
    {
        if (! $department instanceof Department) {
            return null;
        }

        $names = $department->getTranslations('name');

        $label = $names[$locale] ?? $names['en'] ?? null;

        return ['id' => $department->ulid, 'label' => is_string($label) ? $label : $department->code];
    }
}
