<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\CancelImport;
use App\Domain\People\Actions\SaveImportMapping;
use App\Domain\People\Actions\StartEmployeeImport;
use App\Domain\People\Actions\StartImportCommit;
use App\Domain\People\Data\ImportData;
use App\Domain\People\Enums\ImportRowStatus;
use App\Domain\People\Models\Import;
use App\Domain\People\Models\ImportRow;
use App\Domain\People\Support\Imports\EmployeeImportFields;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Spreadsheets\SpreadsheetReader;
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

        $titles = EmployeeImportFields::titles(app()->getLocale());
        $labels = array_combine(array_keys(EmployeeImportFields::all()), $titles);

        return Inertia::render('people/imports/show', [
            'import' => ImportData::fromImport($import),
            'samples' => $this->samples($import),
            'rowErrors' => $import->invalid_rows + $import->failed_rows > 0 ? $this->rowErrors($import) : [],
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

    /**
     * The first three data rows, so the mapping step can show what each column holds.
     *
     * @return list<list<string|null>>
     */
    private function samples(Import $import): array
    {
        $source = $import->getFirstMedia('source');

        if (! $source instanceof Media || ! is_file($source->getPath())) {
            return [];
        }

        $rows = [];

        foreach (SpreadsheetReader::rows($source->getPath(), $source->extension) as $number => $cells) {
            if ($number > 1 && array_filter($cells, fn (?string $c): bool => $c !== null) !== []) {
                $rows[] = $cells;
            }

            if (count($rows) === 3 || $number > 50) {
                break;
            }
        }

        return $rows;
    }

    /**
     * The first 50 rows that need fixing (the full list is in the downloadable report).
     *
     * @return list<array{row: int, errors: array<string, list<string>>}>
     */
    private function rowErrors(Import $import): array
    {
        return array_values($import->rows()
            ->whereIn('status', [ImportRowStatus::Invalid, ImportRowStatus::Failed])
            ->orderBy('row_number')
            ->limit(50)
            ->get(['row_number', 'errors'])
            ->map(fn (ImportRow $row): array => ['row' => $row->row_number, 'errors' => $row->errors ?? []])
            ->all());
    }

    private function authorizeImport(Import $import, User $user): void
    {
        Gate::authorize('people.create');
        abort_unless($import->created_by === $user->id && $import->type === Import::TYPE_EMPLOYEES, 404);
    }
}
