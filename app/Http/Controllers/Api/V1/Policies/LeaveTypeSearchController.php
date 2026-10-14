<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Policies;

use App\Domain\Policies\Models\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Policies\LeaveTypeResource;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Active leave types for pickers and forms (D48). Company reference data: any signed-in person
 * may read it, as everyone requests leave.
 */
final class LeaveTypeSearchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = $request->string('search')->trim()->toString();
        $page = LeaveType::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(fn (Builder $q) => $q->whereRaw('name::text ilike ?', ['%'.addcslashes($search, '%_\\').'%'])->orWhere('code', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('sort_order')->orderBy('id')
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString();

        return LeaveTypeResource::collection($page);
    }
}
