<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\Form;
use App\Domain\Requests\Actions\SaveRequestType;
use App\Domain\Requests\Data\RequestTypeData;
use App\Domain\Requests\Data\RequestTypeInputData;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Request types (settings): what people can ask for, and which form and workflow handle it. */
final class RequestTypesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('requests.configure');

        return Inertia::render('requests/types', [
            'types' => RequestType::query()->with(['form', 'workflow'])->orderBy('sort_order')->orderBy('id')->get()->map(RequestTypeData::fromType(...))->values(),
            'forms' => Form::query()->orderBy('code')->get()->map(fn (Form $form): array => ['id' => $form->ulid, 'code' => $form->code, 'name' => $form->getTranslations('name'), 'published' => $form->published_version_id !== null])->values(),
            'workflows' => WorkflowDefinition::query()->orderBy('code')->get()->map(fn (WorkflowDefinition $w): array => ['id' => $w->ulid, 'code' => $w->code, 'name' => $w->getTranslations('name'), 'published' => $w->published_version_id !== null])->values(),
        ]);
    }

    public function store(RequestTypeInputData $data, SaveRequestType $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('requests.configure');
        $save->handle(Actor::user($user), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request type saved.')]);

        return back();
    }

    public function update(RequestTypeInputData $data, RequestType $type, SaveRequestType $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('requests.configure');
        $save->handle(Actor::user($user), $data, $type);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request type saved.')]);

        return back();
    }
}
