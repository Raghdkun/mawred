<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Actions\WithdrawRequest;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Requests\RequestResource;
use App\Http\Resources\Api\V1\Requests\RequestTypeResource;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Requests for the mobile app (ADR-0012): the types someone can use, their own requests, and
 * submitting or withdrawing one. Same Actions as the web; mutations honour `Idempotency-Key`.
 */
final class RequestsController extends Controller
{
    public function types(): AnonymousResourceCollection
    {
        Gate::authorize('requests.create');

        $types = RequestType::query()->with(['form', 'workflow'])->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (RequestType $type): bool => $type->isAvailable() && ! $type->isRaisedElsewhere() && $type->form?->published_version_id !== null && $type->workflow?->published_version_id !== null)
            ->values();

        return RequestTypeResource::collection($types);
    }

    public function type(RequestType $type): RequestTypeResource
    {
        Gate::authorize('requests.create');
        abort_unless($type->isAvailable(), 404);

        return new RequestTypeResource($type->load('form'));
    }

    public function index(Request $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $page = EmployeeRequest::query()->with(['type', 'requester'])->where('requester_user_id', $user->id)
            ->latest('submitted_at')->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 25))->withQueryString();

        return RequestResource::collection($page);
    }

    public function show(EmployeeRequest $employeeRequest): RequestResource
    {
        abort_unless(Gate::allows('view', $employeeRequest), 404);

        return new RequestResource($employeeRequest->load(['type', 'requester']));
    }

    public function store(Request $request, RequestType $type, SubmitRequest $submit, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('requests.create');
        $request->validate(['values' => ['present', 'array']]);

        /** @var array<string, mixed> $values */
        $values = $request->input('values');
        $created = $submit->handle(Actor::user($user, Channel::Api), $type, $values);

        return (new RequestResource($created->load(['type', 'requester'])))->response()->setStatusCode(201);
    }

    public function withdraw(Request $request, EmployeeRequest $employeeRequest, WithdrawRequest $withdraw, #[CurrentUser] User $user): RequestResource
    {
        abort_unless(Gate::allows('view', $employeeRequest), 404);
        Gate::authorize('withdraw', $employeeRequest);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $withdraw->handle(Actor::user($user, Channel::Api), $employeeRequest, $request->filled('reason') ? $request->string('reason')->toString() : null);

        return new RequestResource($employeeRequest->refresh()->load(['type', 'requester']));
    }
}
