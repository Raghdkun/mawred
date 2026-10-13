<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Audit;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit log (handoff screen 43). Immutable: there are no destructive actions here.
 */
final class AuditLogController extends Controller
{
    public const array LOGS = ['model', 'auth', 'access', 'setup'];

    public function index(Request $request, AuditLogQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('audit.view');

        $filters = $request->validate([
            'log' => ['nullable', 'in:'.implode(',', self::LOGS)],
            'event' => ['nullable', 'string', 'max:64'],
            'actor' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var array{log?: string|null, event?: string|null, actor?: string|null, from?: string|null, to?: string|null, search?: string|null} $filters */
        $page = $query->filter($filters)->visibleTo($viewer)->cursorPaginate(50)->withQueryString();

        return Inertia::render('audit/index', [
            'entries' => [
                'data' => array_map(AuditEntryData::fromActivity(...), $page->items()),
                'nextCursor' => $page->nextCursor()?->encode(),
                'prevCursor' => $page->previousCursor()?->encode(),
            ],
            'filters' => (object) array_filter($filters),
            'logs' => self::LOGS,
        ]);
    }
}
