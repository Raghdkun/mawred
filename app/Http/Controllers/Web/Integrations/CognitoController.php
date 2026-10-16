<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Integrations;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormSchema;
use App\Domain\Integrations\Actions\AssignCognitoReceipt;
use App\Domain\Integrations\Actions\DiscardCognitoReceipt;
use App\Domain\Integrations\Actions\ReplayCognitoReceipt;
use App\Domain\Integrations\Actions\RotateCognitoToken;
use App\Domain\Integrations\Actions\SaveCognitoSource;
use App\Domain\Integrations\Cognito\CognitoMapping;
use App\Domain\Integrations\Contracts\CognitoApplications;
use App\Domain\Integrations\Data\CognitoSourceData;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cognito detail (handoff 41): sources with their webhook URLs, field mapping with the sample
 * payload, and the submission log with replay, pick-a-person and discard (D61).
 */
final class CognitoController extends Controller
{
    public function __construct(private readonly CognitoApplications $applications) {}

    public function index(Request $request): Response
    {
        Gate::authorize('integrations.configure');
        $request->validate(['status' => ['nullable', Rule::enum(ReceiptStatus::class)], 'source' => ['nullable', 'string', 'size:26']]);

        $sources = CognitoSource::query()->with('requestType')->orderBy('name')->get();
        $sourceId = $request->filled('source') ? $sources->firstWhere('ulid', $request->string('source')->toString())?->id : null;
        $receipts = CognitoReceipt::query()->with('source')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($sourceId !== null, fn ($q) => $q->where('cognito_source_id', $sourceId))
            ->latest('created_at')->latest('id')->paginate(25)->withQueryString();
        $numbers = EmployeeRequest::query()->whereIn('id', $receipts->getCollection()->pluck('request_id')->filter())->get(['id', 'ulid', 'number'])->keyBy('id');

        return Inertia::render('integrations/cognito', [
            'sources' => $sources->map(fn (CognitoSource $source): array => $this->sourceRow($source))->values(),
            'receipts' => $receipts->through(fn (CognitoReceipt $receipt): array => [
                'id' => $receipt->ulid,
                'source' => $receipt->source?->name,
                'event' => $receipt->event,
                'entryId' => $receipt->entry_id,
                'status' => $receipt->status->value,
                'error' => $receipt->error,
                'canReplay' => $receipt->status->canReplay(),
                'request' => ($row = $numbers[$receipt->request_id] ?? null) instanceof EmployeeRequest ? ['id' => $row->ulid, 'number' => $row->number] : null,
                'at' => $receipt->created_at->toIso8601String(),
            ]),
            'requestTypes' => $this->requestTypes(),
            'filters' => ['status' => $request->input('status'), 'source' => $request->input('source')],
            'statuses' => array_map(fn (ReceiptStatus $s): string => $s->value, ReceiptStatus::cases()),
        ]);
    }

    public function store(CognitoSourceData $data, SaveCognitoSource $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        [$source, $token] = $save->handle(Actor::user($user), $data);
        Inertia::flash('urls', $this->urls((string) $token));

        return to_route('integrations.cognito.show', $source);
    }

    public function show(CognitoSource $source, #[CurrentUser] User $user): Response
    {
        Gate::authorize('integrations.configure');

        if ($source->sample_payload !== null) {
            activity('integrations')->causedBy($user)->performedOn($source)->event('sample.viewed')->log('Cognito sample viewed');
        }

        $mapping = CognitoMapping::of($source);

        return Inertia::render('integrations/cognito-source', [
            'source' => [
                ...$this->sourceRow($source),
                'formUrl' => $source->form_url,
                'mapping' => [
                    'fields' => $mapping->fields,
                    'requesterBy' => $mapping->requesterBy,
                    'requesterPath' => $mapping->requesterPath,
                    'entryPath' => $mapping->entryPath,
                    'versionPath' => $mapping->versionPath,
                    'prefill' => $mapping->prefill,
                ],
                'captureSample' => $source->capture_sample,
                'sample' => $source->sample_payload,
                'sampleCapturedAt' => $source->sample_captured_at?->toIso8601String(),
            ],
            'formFields' => $this->formFields($source),
            'requestTypes' => $this->requestTypes(),
            'urlTemplate' => $this->urls('{token}'),
        ]);
    }

    public function update(CognitoSourceData $data, CognitoSource $source, SaveCognitoSource $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $save->handle(Actor::user($user), $data, $source);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Source saved.')]);

        return back();
    }

    public function rotate(CognitoSource $source, RotateCognitoToken $rotate, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        Inertia::flash('urls', $this->urls($rotate->handle(Actor::user($user), $source)));

        return back();
    }

    public function capture(CognitoSource $source): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $source->forceFill(['capture_sample' => true])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Submit a test entry in Cognito; it will be kept as the sample.')]);

        return back();
    }

    /** The raw payload for the JSON viewer (an audited read). */
    public function receipt(CognitoReceipt $receipt, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('integrations.configure');
        activity('integrations')->causedBy($user)->event('cognito.payload_viewed')->withProperties(['receipt' => $receipt->ulid])->log('Cognito payload viewed');

        return response()->json(['payload' => $receipt->payload]);
    }

    public function assign(Request $request, CognitoReceipt $receipt, AssignCognitoReceipt $assign, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $request->validate(['employee' => ['required', 'string', 'exists:employees,ulid']]);
        $assign->handle(Actor::user($user), $receipt, Employee::query()->where('ulid', $request->string('employee')->toString())->firstOrFail());

        return back();
    }

    public function replay(CognitoReceipt $receipt, ReplayCognitoReceipt $replay, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $replay->handle(Actor::user($user), $receipt);

        return back();
    }

    public function discard(CognitoReceipt $receipt, DiscardCognitoReceipt $discard, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $discard->handle(Actor::user($user), $receipt);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceRow(CognitoSource $source): array
    {
        $type = $source->requestType;

        return [
            'id' => $source->ulid,
            'name' => $source->name,
            'requestType' => $type instanceof RequestType ? ['id' => $type->ulid, 'name' => $type->getTranslations('name')] : null,
            'posting' => $type instanceof RequestType ? null : $this->applications->posting($source, app()->getLocale()),
            'isActive' => $source->is_active,
            'tokenLast4' => $source->token_last4,
            'rotatedAt' => $source->rotated_at?->toIso8601String(),
            'receivedToday' => CognitoReceipt::query()->where('cognito_source_id', $source->id)->where('created_at', '>=', now()->subDay())->count(),
            'waiting' => CognitoReceipt::query()->where('cognito_source_id', $source->id)->where('status', ReceiptStatus::NeedsPerson->value)->count(),
        ];
    }

    /**
     * @return array{submit: string, update: string, delete: string}
     */
    private function urls(string $token): array
    {
        return [
            'submit' => url("/hooks/cognito/{$token}/submit"),
            'update' => url("/hooks/cognito/{$token}/update"),
            'delete' => url("/hooks/cognito/{$token}/delete"),
        ];
    }

    /**
     * @return list<array{id: string, name: mixed}>
     */
    private function requestTypes(): array
    {
        return array_values(RequestType::query()->orderBy('sort_order')->get()->map(fn (RequestType $type): array => ['id' => $type->ulid, 'name' => $type->getTranslations('name')])->all());
    }

    /**
     * @return list<array{key: string, label: mixed, type: mixed, required: bool}>
     */
    private function formFields(CognitoSource $source): array
    {
        $type = $source->requestType;
        $form = $type instanceof RequestType ? Form::withTrashed()->find($type->form_id) : $this->applications->form($source);
        $version = $form instanceof Form && $form->published_version_id !== null ? FormVersion::query()->find($form->published_version_id) : null;

        if (! $version instanceof FormVersion) {
            return [];
        }

        return array_values(array_map(fn (array $field): array => [
            'key' => is_string($field['key'] ?? null) ? $field['key'] : '',
            'label' => $field['label'] ?? null,
            'type' => $field['type'] ?? null,
            'required' => ($field['required'] ?? false) === true,
        ], array_filter(FormSchema::fields($version->schema), fn (array $field): bool => ! in_array($field['type'] ?? null, ['note', 'calculated'], true))));
    }
}
