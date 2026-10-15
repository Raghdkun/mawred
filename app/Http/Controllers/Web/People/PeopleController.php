<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Data\AssetData;
use App\Domain\People\Data\AssignmentData;
use App\Domain\People\Data\EmployeeDocumentData;
use App\Domain\People\Data\EmployeeSummaryData;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\HistoryEntryData;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\AssetAssignment;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Domain\People\Queries\EmployeeDirectoryQuery;
use App\Domain\People\Support\EmployeeProfiles;
use App\Domain\People\Support\HistoryNames;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * People directory (handoff screen 7) and employee profile (screen 8).
 */
final class PeopleController extends Controller
{
    public function index(Request $request, EmployeeDirectoryQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('people.view');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'exists:branches,ulid'],
            'department' => ['nullable', 'string', 'exists:departments,ulid'],
            'status' => ['nullable', Rule::in(['current', 'all', ...array_map(fn (EmployeeStatus $s): string => $s->value, EmployeeStatus::cases())])],
            'sort' => ['nullable', Rule::in(EmployeeDirectoryQuery::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'view' => ['nullable', Rule::in(['table', 'grid', 'org'])],
        ]);

        $branch = $request->filled('branch') ? Branch::query()->where('ulid', $request->string('branch')->toString())->first() : null;
        $department = $request->filled('department') ? Department::query()->where('ulid', $request->string('department')->toString())->first() : null;
        $status = $request->string('status', 'current')->toString();

        $query->search($request->string('search')->toString())
            ->inBranches($branch instanceof Branch ? [$branch->id] : [])
            ->inDepartment($department)
            ->sortBy($request->string('sort', 'name')->toString(), $request->input('direction') === 'desc')
            ->namesIn(app()->getLocale());

        if ($status !== 'current') {
            $query->withStatuses($status === 'all' ? [] : [EmployeeStatus::from($status)]);
        }

        $page = $query->visibleTo($viewer)->cursorPaginate(50)->withQueryString();
        $locale = app()->getLocale();

        return Inertia::render('people/index', [
            'employees' => [
                'data' => array_map(EmployeeSummaryData::fromEmployee(...), $page->items()),
                'nextCursor' => $page->nextCursor()?->encode(),
                'prevCursor' => $page->previousCursor()?->encode(),
            ],
            'filters' => [
                'search' => $request->input('search'),
                'branch' => $branch instanceof Branch ? ['id' => $branch->ulid, 'label' => $branch->getTranslation('name', $locale)] : null,
                'department' => $department instanceof Department ? ['id' => $department->ulid, 'label' => $department->getTranslation('name', $locale)] : null,
                'status' => $status,
                'sort' => $request->input('sort', 'name'),
                'direction' => $request->input('direction', 'asc'),
                'view' => $request->input('view', 'table'),
            ],
            'options' => [
                ...OrganizationOptions::grades(),
                'employmentTypes' => OrganizationOptions::entries(CatalogKind::EmploymentType),
            ],
            'can' => [
                'create' => Gate::allows('people.create'),
                'update' => Gate::allows('people.update'),
                'import' => Gate::allows('people.create'),
            ],
        ]);
    }

    public function store(HireEmployeeData $data, HireEmployee $hire, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('people.create');

        $employee = $hire->handle(Actor::user($user), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee added.')]);

        return to_route('people.show', $employee);
    }

    public function show(Request $request, Employee $employee, EmployeeProfiles $profiles, ScopeResolver $resolver, #[CurrentUser] User $viewer): Response
    {
        // Out-of-scope people answer 404, so the profile URL never confirms they exist.
        abort_unless(Gate::allows('view', $employee), 404);

        $canSeeSalary = $resolver->fieldAccess($viewer, 'employee.salary') instanceof FieldAccess;
        $history = $employee->history()->latest('effective_on')->orderByDesc('id')->get();
        $canUpdate = Gate::allows('update', $employee);

        return Inertia::render('people/show', [
            'profile' => $profiles->for($viewer, $employee),
            'tab' => $request->string('tab', 'overview')->toString(),
            'assignments' => $employee->assignments()
                ->with(['branch', 'department', 'position', 'manager', 'employmentType', 'grade'])
                ->orderByDesc('effective_from')->orderByDesc('id')->get()
                ->map(fn (EmployeeAssignment $a): AssignmentData => AssignmentData::fromAssignment($a)),
            'documents' => $employee->documents()->with(['type', 'media'])->orderByRaw('expires_on asc nulls last')->orderByDesc('id')->get()
                ->map(fn (EmployeeDocument $d): EmployeeDocumentData => EmployeeDocumentData::fromDocument($d)),
            'history' => $history->map(fn (EmploymentHistoryEntry $entry): HistoryEntryData => HistoryEntryData::fromEntry($entry, $canSeeSalary)),
            'historyNames' => (object) HistoryNames::for($history),
            'defaultCurrency' => $employee->currentBranch()->value('default_currency') ?? 'SAR',
            'assets' => $employee->assets()->orderByRaw('returned_on is not null')->latest('assigned_on')->get()
                ->map(fn (AssetAssignment $asset): AssetData => AssetData::fromAssignment($asset)),
            'directReports' => $employee->directReports()
                ->with(['currentBranch:id,ulid,code,name', 'currentDepartment:id,ulid,code,name', 'currentPosition:id,ulid,code,title', 'currentManager:id,ulid,name_en,name_ar,preferred_name'])
                ->orderBy('name_en')->limit(50)->get()
                ->map(fn (Employee $e): EmployeeSummaryData => EmployeeSummaryData::fromEmployee($e)),
            'options' => [
                ...OrganizationOptions::grades(),
                'employmentTypes' => OrganizationOptions::entries(CatalogKind::EmploymentType),
                'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('code')->get()
                    ->map(fn (DocumentType $type): array => ['id' => $type->ulid, 'code' => $type->code, 'name' => $type->getTranslations('name'), 'requiresExpiry' => $type->requires_expiry]),
            ],
            'can' => [
                'update' => $canUpdate,
                'terminate' => $canUpdate && $employee->status !== EmployeeStatus::Terminated,
                'invite' => $canUpdate && $employee->user_id === null,
                'policies' => Gate::allows('policies.view'),
                'leave' => Gate::allows('viewLeave', $employee),
                'salary' => [
                    'read' => $canSeeSalary,
                    'write' => $canUpdate && $resolver->fieldAccess($viewer, 'employee.salary')?->value === 'write',
                ],
            ],
        ]);
    }
}
