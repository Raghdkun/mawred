# ADR-0009: In-house workflow engine (versioned graphs, queued, idempotent handlers)

- Status: Accepted (Phase 3b)
- Date: 2026-10-13

## Context
The engine needs versioned graphs edited in React Flow, the full node set (approval variants, conditions, parallel split/join, timers, SLA, tasks, record updates, webhooks, sub-workflows), assignee resolvers, delegation, out-of-app approvals, a simulator, a dead-letter view and full audit. External engines (Temporal, Camunda/Zeebe, n8n) add infrastructure we can't justify on one VPS, and they don't model HR approval semantics (delegation, return-for-changes, scope-aware assignees).

## Decision
- Built in-house on Laravel queues (Horizon). Tables are `workflow_definitions`, `workflow_versions`, `workflow_instances`, `workflow_steps`, `workflow_tasks`, `workflow_timeline`, `action_tokens` (see architecture §6).
- **Graph format** `mawred.workflow/v1`: `{ nodes: [{id, type, config}], edges: [{id, source, target, sourceHandle?, condition?}] }`. React Flow UI positions are stored separately in `layout`, so they don't affect the checksum.
- **Node handlers**: one class per type implementing `NodeHandler { type(): string; validate(config, ctx): ValidationResult; handle(StepContext): NodeResult; simulate(StepContext): NodeResult }`, registered in `NodeRegistry`. The frontend node registry mirrors the type ids and config schemas (shared via generated TS types).
- **Determinism**: given the same step inputs and decisions, a handler produces the same outputs. Time comes from an injected clock. Round-robin state is the only exception and is recorded in the step output.
- **Idempotency**: steps have a unique `(instance_id, node_id, iteration, branch_key)`. Side effects carry the step ULID (unique constraints downstream; outbound webhooks send an `Idempotency-Key` header).
- **Concurrency**: a per-instance lock (`Cache::lock` + row lock). Jobs are `ShouldBeUnique` per step.
- **Failure**: backoff `[10, 60, 300, 900]`. After that the step is `failed`, the instance is marked `attention`, and it shows in the dead-letter view with retry/skip/cancel (audited).
- **Timers**: DB columns are the source of truth, swept every minute by `workflows:tick`.
- **Versioning**: instances pin `workflow_version_id`. Publishing never mutates existing versions. A migration tool to move running instances to a new version is **out of scope for v1**.
- **Sub-workflow**: the child instance links to its parent step, and the parent waits for the child's end status.
- **Webhook out**: an HMAC-SHA256 signature header with timestamp, a per-endpoint secret in the credential vault, 10 s timeout, and retries.
- **Out-of-app approvals**: 32-byte random tokens stored as sha256 in `action_tokens`, single-use, default TTL 72 h (configurable), invalidated when the task is decided through any channel. The confirmation page shows a request summary filtered by the assignee's field permissions. Request types flagged **sensitive** (salary certificate, resignation, profile/bank change, offers) require login, plus 2FA if the role requires it, before the decision is accepted (D24).
- **Simulator**: the same engine over an in-memory repository with `SimulationMode` handlers. It returns the trace and is never persisted.

## Consequences
- + Full control over HR semantics, testable per handler, no extra infrastructure.
- − We own correctness: a heavy scenario-test suite per seeded request type (approve, reject, return, escalate, delegate, cancel, parallel) is part of done.

## Implementation notes (Phase 3b)
- UI positions live on each node (`position`) rather than in a separate `layout`; the engine ignores them. The builder mirrors x in Arabic so the flow follows the reading direction; stored positions are always LTR.
- Handlers expose `handles(config)` (named outputs) instead of a separate `simulate()`: the **simulator runs the real engine** with `simulating = true` inside a transaction that is always rolled back, so notify, webhook and record-update nodes skip their side effects and nothing persists.
- Locking: `Cache::lock("wf:{id}")` plus row locks; follow-up steps, sub-workflow children and parent wake-ups are dispatched **after** the lock is released (the sync queue would otherwise deadlock). Retries (10 s / 1 min / 5 min / 15 min) are swept by `workflows:tick`, never dispatched inline; a step that fails them all puts the instance in `attention` (dead-letter view: retry, skip, cancel).
- Nested parallel blocks are supported only when every split has a matching join; a join waits for all branches of its split.
- Link approvals: 32-byte random tokens, only the sha256 stored, single-use, 72 h (configurable); opening shows a confirmation; private request types need the assignee signed in (D24).
- Hiring or ending someone's employment by hand starts the seeded ONBOARDING / OFFBOARDING checklists (not imports or seeds).
