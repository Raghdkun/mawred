<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\CancelImport;
use App\Domain\People\Actions\SaveImportMapping;
use App\Domain\People\Actions\StartEmployeeImport;
use App\Domain\People\Actions\StartImportCommit;
use App\Domain\People\Data\ImportData;
use App\Domain\People\Models\Import;
use App\Domain\People\Support\Imports\EmployeeImportFields;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Spreadsheets\SpreadsheetWriter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * People import wizard (handoff screen 9, desktop-only): upload → map → dry run → import.
 * Imports are private to the person who started them.
 */
final class ImportsController extends Controller
{
    public function create(#[CurrentUser] User $user): Response
    {
        Gate::authorize('people.create');

        return Inertia::render('people/imports/create', [
            'recent' => Import::query()->where('created_by', $user->id)->latest('id')->limit(5)->get()
                ->map(fn (Import $import): ImportData => ImportData::fromImport($import)),
        ]);
    }

    public function store(Request $request, StartEmployeeImport $start, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('people.create');

        $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $import = $start->handle(Actor::user($user), $file);

        return to_route('people.imports.show', $import);
    }

    public function show(Import $import, #[CurrentUser] User $user): Response
    {
        $this->authorizeImport($import, $user);

        $labels = [];
        foreach (EmployeeImportFields::all() as $field => $aliases) {
            $labels[$field] = str_starts_with($field, 'custom.') ? ($aliases[1] ?? $aliases[0] ?? $field) : $field;
        }

        return Inertia::render('people/imports/show', [
            'import' => ImportData::fromImport($import),
            'fields' => $labels,
            'requiredForCreate' => EmployeeImportFields::REQUIRED_FOR_CREATE,
        ]);
    }

    public function updateMapping(Request $request, Import $import, SaveImportMapping $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeImport($import, $user);

        $request->validate(['mapping' => ['present', 'array'], 'mapping.*' => ['nullable', 'string', 'max:80']]);

        /** @var array<string, string|null> $mapping */
        $mapping = $request->array('mapping');
        $save->handle($import, $mapping);

        return back();
    }

    public function commit(Import $import, StartImportCommit $commit, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeImport($import, $user);

        $commit->handle($import);

        return back();
    }

    public function destroy(Import $import, CancelImport $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeImport($import, $user);

        $cancel->handle($import);

        return to_route('people.imports.create');
    }

    public function errors(Import $import, #[CurrentUser] User $user): BinaryFileResponse
    {
        $this->authorizeImport($import, $user);

        $report = $import->getFirstMedia('error_report');
        abort_if(! $report instanceof Media, 404);

        return response()->download($report->getPath(), "import-errors-{$import->ulid}.xlsx");
    }

    public function template(): BinaryFileResponse
    {
        Gate::authorize('people.create');

        $path = storage_path('app/tmp/employee-import-template-'.app()->getLocale().'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        SpreadsheetWriter::write($path, EmployeeImportFields::titles(app()->getLocale()), []);

        return response()->download($path, 'mawred-employee-import.xlsx')->deleteFileAfterSend();
    }

    private function authorizeImport(Import $import, User $user): void
    {
        Gate::authorize('people.create');
        abort_unless($import->created_by === $user->id && $import->type === Import::TYPE_EMPLOYEES, 404);
    }
}
