# ADR-0012: REST API v1 conventions

- Status: Proposed
- Date: 2026-10-13

## Decision
- **Coverage per phase (D27)**: employee- and approver-facing features (profile, requests, inbox and approvals, leave, attendance and clock-in, roster view, calendar, notifications, training enrolment, documents) plus every endpoint the web app's async widgets use. Admin builders (form builder, workflow canvas, policy editor, report builder, integrations wizards) stay web-only until a client needs them.
- **Base and versioning**: `/api/v1/...`. Breaking changes go to `/api/v2`. Deprecations are announced with a `Sunset` header.
- **Auth**:
  - Sanctum personal access tokens with abilities for the Flutter app (device-named tokens, revocable from "Active sessions") and the Integration Bot role.
  - Sanctum stateful (cookie + CSRF) for the first-party web app's async widgets.
  - The same Policies and ScopeResolver apply in both cases.
- **Controllers**: API controllers call the same Actions as Inertia controllers (ADR-0002). Responses use API Resources only, with `id` = ULID, ISO-8601 UTC instants and `YYYY-MM-DD` local dates, and translatable fields returned as `{ "en": "...", "ar": "..." }` plus a resolved `*_label` for `Accept-Language`.
- **Lists**:
  - Cursor pagination `{ data, meta: { next_cursor, prev_cursor, per_page } }`, with `per_page` ≤ 100.
  - `spatie/laravel-query-builder` with explicit allow-lists: `filter[...]`, `sort=-created_at`, `include=`.
  - The same `Queries\*` classes back web and API.
- **Errors**: `{ "error": { "code": "leave.insufficient_balance", "message": "...localised...", "details": { "field": ["..."] }, "trace_id": "..." } }`. Codes are stable and documented. Validation is 422, domain rule violations are 409 or 422 with a code, authorization failures are 403, and unknown or out-of-scope records are **404, not 403**, so existence doesn't leak.
- **Idempotency**:
  - Mutating endpoints accept `Idempotency-Key` (required for the Flutter app on POST).
  - `idempotency_keys (owner, key, request_hash, status, response, expires_at)`, 24 h TTL.
  - The same key with a different hash returns 422. A key that is still in progress returns 409.
- **Rate limits**: per token/user (`api` 120/min) and stricter for auth and exports.
- **Docs**: Scramble generates OpenAPI 3.1 at `/docs/api` (permission `api.docs`). A spec snapshot is committed, and CI diffs it to catch accidental breaking changes.
- **Webhooks in** (Cognito, Meta, WhatsApp sidecar) are not under `/api/v1`. They use `/hooks/{provider}/{token}` with their own verification.
