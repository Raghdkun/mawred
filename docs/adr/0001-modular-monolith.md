# ADR-0001: Modular monolith with domain folders

- Status: Proposed
- Date: 2026-10-13

## Context
There are 18 business domains, one team, one VPS and one database. Microservices would add network failure modes, distributed transactions and deployment overhead, and the scale doesn't call for them. Without structure, though, a Laravel app of this size tends to turn into a single `app/Models` folder with logic spread through controllers.

## Decision
- One Laravel application with `app/Domain/<Domain>/{Models,Actions,Data,Queries,Policies,Events,Listeners,Jobs,Enums,Exceptions,Support,Contracts}`.
- HTTP lives outside the domain layer in `app/Http/Controllers/{Web,Api/V1,Public}`.
- Shared kernel in `app/Support`. No domain depends on another domain's internals except through `Actions`, `Queries`, `Events`, `Data` and `Contracts`. Eloquent relations across domains are allowed for reads.
- Boundaries are enforced by Pest arch tests (`tests/Arch`):
  - `App\Domain` does not use `App\Http`, `Inertia`, `Illuminate\Http\Request`.
  - Controllers do not use `Illuminate\Support\Facades\DB` or call static `create`/`update` on models.
  - `Actions` are final with a single public `handle` method.
  - `Queries` extend `App\Domain\Access\Queries\ScopedQuery`.
  - Every controller method is authorized (see ADR-0005).
- Migrations are named `YYYY_MM_DD_HHMMSS_<domain>_<what>.php` so the domain is visible in the migration list.

## Consequences
- + One deployable unit, real DB transactions, and refactoring across domains stays cheap.
- + Domains can later be extracted, because the seams are already explicit.
- − Arch tests must be maintained, and some cross-domain reads are intentionally pragmatic.

## Alternatives
- Laravel default layout: rejected because it doesn't scale to 18 domains.
- Separate composer packages per domain: rejected as heavy tooling for no runtime benefit.
- Microservices: rejected, see Context.
