# ADR-0002: Actions are the single write path

- Status: Proposed
- Date: 2026-10-13

## Context
The Inertia web app, the `/api/v1` API (Flutter, bots), queued jobs, the workflow engine (Update Record nodes), the Cognito webhook and import jobs all trigger the same business operations. If each entry point has its own code, behaviour diverges.

## Decision
- Every state change is a `final class <Verb><Noun>` in `Domain/*/Actions` with one public `handle(...)` method. Dependencies come through constructor injection. Inputs are typed `Data` objects or models.
- Actions:
  - run in `DB::transaction` when they write;
  - enforce **business invariants** (overlaps, balances, state transitions) and throw domain exceptions (`DomainRuleViolation` with a stable `code`);
  - emit domain events (dispatched after commit);
  - record audit entries with the given `Actor` (user, system, integration bot, or out-of-app channel).
- **Authorization** happens at the boundary (controller policy check, webhook signature, action token). Actions assume their caller is authorised. System callers use `Actor::system('reason')`.
- Controllers and API controllers are thin: authorize → build Data → `$action->handle()` → Inertia page/redirect or API Resource.
- Domain exceptions map to HTTP centrally: 422 with `error.code` for the API, flashed errors for Inertia.

## Consequences
- + No logic duplication. Actions are unit-testable without HTTP. Workflows reuse them directly.
- − Discipline is required: arch tests stop controllers from writing to models directly.
