<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Forms;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\ArchiveForm;
use App\Domain\Forms\Actions\DuplicateForm;
use App\Domain\Forms\Actions\SaveFormSettings;
use App\Domain\Forms\Data\FormData;
use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Models\Form;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forms list and settings (ADR-0008). Forms are company-wide configuration (D40): gated by
 * forms.* with no data scope.
 */
final class FormsController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('forms.view');
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $search = $request->string('search')->trim()->toString();
        $forms = Form::query()
            ->with(['publishedVersion', 'draft'])
            ->withCount('submissions')
            ->when($search !== '', fn ($q) => $q->where(fn (Builder $q) => $q->whereRaw('name::text ilike ?', ['%'.addcslashes($search, '%_\\').'%'])->orWhere('code', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
            ->orderByRaw("name->>'en'")
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Form $form): FormData => FormData::fromForm($form));

        return Inertia::render('forms/index', [
            'forms' => $forms,
            'filters' => ['search' => $request->input('search')],
            'can' => $this->can(),
        ]);
    }

    public function store(FormSettingsData $data, SaveFormSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.create');

        $form = $save->handle(Actor::user($user), $data);

        return to_route('forms.builder', $form);
    }

    public function update(FormSettingsData $data, Form $form, SaveFormSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.update');

        $save->handle(Actor::user($user), $data, $form);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form settings saved.')]);

        return back();
    }

    public function duplicate(Request $request, Form $form, DuplicateForm $duplicate, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('forms.create');
        $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
        ]);

        $copy = $duplicate->handle(Actor::user($user), $form, $request->string('code')->toString(), [
            'en' => $request->string('name.en')->toString(),
            'ar' => $request->filled('name.ar') ? $request->string('name.ar')->toString() : null,
        ]);

        return to_route('forms.builder', $copy);
    }

    public function destroy(Form $form, ArchiveForm $archive): RedirectResponse
    {
        Gate::authorize('forms.delete');

        $archive->handle($form);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form archived.')]);

        return to_route('forms.index');
    }

    /**
     * @return array<string, bool>
     */
    private function can(): array
    {
        return [
            'create' => Gate::allows('forms.create'),
            'update' => Gate::allows('forms.update'),
            'delete' => Gate::allows('forms.delete'),
        ];
    }
}
