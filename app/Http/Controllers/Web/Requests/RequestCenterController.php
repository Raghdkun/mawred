<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Integrations\Cognito\CognitoMapping;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Data\RequestTypeData;
use App\Domain\Requests\Models\RequestType;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Request center (handoff 10) and the request form (handoff 11).
 */
final class RequestCenterController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        Gate::authorize('requests.create');

        $types = RequestType::query()->with(['form', 'workflow'])->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (RequestType $type): bool => $type->isAvailable())
            ->map(fn (RequestType $type): RequestTypeData => $this->withExternalLink(RequestTypeData::fromType($type), $type, $user))
            ->filter(fn (RequestTypeData $type): bool => $type->ready && ($type->source === 'native' || $type->externalUrl !== null))
            ->values();

        return Inertia::render('requests/center', ['types' => $types, 'showSource' => Gate::allows('requests.configure')]);
    }

    public function create(RequestType $type, FormContext $context, #[CurrentUser] User $user): Response|SymfonyResponse
    {
        Gate::authorize('requests.create');
        abort_unless($type->isAvailable(), 404);

        // Cognito-backed types are filled in on Cognito (D60).
        if ($type->source === 'cognito') {
            $link = $this->withExternalLink(RequestTypeData::fromType($type->load(['form', 'workflow'])), $type, $user)->externalUrl;
            abort_if($link === null, 404);

            return Inertia::location($link);
        }

        $version = $this->publishedVersion($type);

        return Inertia::render('requests/create', [
            'type' => RequestTypeData::fromType($type->load(['form', 'workflow'])),
            'schema' => $version->schema,
            'context' => $context->for($user),
        ]);
    }

    public function store(Request $request, RequestType $type, SubmitRequest $submit, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('requests.create');
        $request->validate(['values' => ['array']]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $created = $submit->handle(Actor::user($user), $type, $values);
        Inertia::flash('submitted', true);

        return to_route('requests.show', $created);
    }

    public function upload(Request $request, RequestType $type, StoreFormUpload $store, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('requests.create');
        $request->validate(['field' => ['required', 'string', 'max:40'], 'file' => ['required', 'file', 'max:20480']]);

        $version = $this->publishedVersion($type);
        $form = Form::withTrashed()->findOrFail($version->form_id);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $upload = $store->handle($form, $version, $request->string('field')->toString(), $file, $user, null);

        return response()->json(['id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime], 201);
    }

    private function publishedVersion(RequestType $type): FormVersion
    {
        $version = $type->form()->first()?->publishedVersion()->first();
        abort_unless($version instanceof FormVersion, 404);

        return $version;
    }

    /** The prefilled Cognito link for this person (D60), on Cognito-backed types. */
    private function withExternalLink(RequestTypeData $data, RequestType $type, User $user): RequestTypeData
    {
        if ($type->source !== 'cognito') {
            return $data;
        }

        $source = CognitoSource::query()->where('request_type_id', $type->id)->where('is_active', true)->whereNotNull('form_url')->first();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if ($source instanceof CognitoSource && $employee instanceof Employee && is_string($source->form_url)) {
            $data->externalUrl = CognitoMapping::of($source)->prefillUrl($source->form_url, $employee, $user, app()->getLocale());
        }

        return $data;
    }
}
