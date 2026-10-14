<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\People;

use App\Domain\Access\Models\User;
use App\Domain\People\Actions\DeleteEmployeeDocument;
use App\Domain\People\Actions\SaveEmployeeDocument;
use App\Domain\People\Data\EmployeeDocumentInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents (screen 8 Documents). Files live on the private disk and are only served
 * here, to people who can see the employee; every download is audited.
 */
final class EmployeeDocumentsController extends Controller
{
    private const array FILE_RULES = ['file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'];

    public function store(Request $request, EmployeeDocumentInputData $data, Employee $employee, SaveEmployeeDocument $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        $request->validate(['file' => ['required', ...self::FILE_RULES]]);

        $save->handle(Actor::user($user), $employee, $data, $this->file($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document added.')]);

        return back();
    }

    public function update(Request $request, EmployeeDocumentInputData $data, Employee $employee, EmployeeDocument $document, SaveEmployeeDocument $save, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        abort_unless($document->employee_id === $employee->id, 404);
        $request->validate(['file' => ['nullable', ...self::FILE_RULES]]);

        $save->handle(Actor::user($user), $employee, $data, $this->file($request), $document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function destroy(Employee $employee, EmployeeDocument $document, DeleteEmployeeDocument $delete): RedirectResponse
    {
        $this->authorizeUpdate($employee);
        abort_unless($document->employee_id === $employee->id, 404);

        $delete->handle($document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Archived.')]);

        return back();
    }

    public function download(Employee $employee, EmployeeDocument $document, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless(Gate::allows('view', $employee) && $document->employee_id === $employee->id, 404);

        $file = $document->getFirstMedia('file');
        abort_if(! $file instanceof Media, 404);

        activity('documents')->causedBy($user)->performedOn($document)->event('downloaded')
            ->withProperties(['employee' => $employee->ulid])->log('Employee document downloaded');

        return $file->toInlineResponse(request());
    }

    private function authorizeUpdate(Employee $employee): void
    {
        abort_unless(Gate::allows('view', $employee), 404);
        Gate::authorize('update', $employee);
    }

    private function file(Request $request): ?UploadedFile
    {
        $file = $request->file('file');

        return $file instanceof UploadedFile ? $file : null;
    }
}
