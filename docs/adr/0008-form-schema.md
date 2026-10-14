# ADR-0008: Mawred Form Schema and a shared rules language

- Status: Accepted (Phase 3a)
- Date: 2026-10-13

## Context
Forms must validate identically on the client (zod) and the server (Laravel rules, authoritative). They need conditional logic, calculations and versioning. Workflow condition nodes also need rules over form data and requester attributes. Free-text formula evaluation is forbidden.

## Decision
- **MFS v1** (`mawred.form/v1`) is a JSON document: `pages[] → sections[] → fields[]`.
  - Each field has a `key`, `type`, translatable `label/help/placeholder`, `validation` (typed per field type), `default` (literal or context ref `@user.employee.branch`), `visibility`/`required`/`set` rules, and `options` (static, or a data source such as employees, departments, leave types).
  - The meta-schema lives in `resources/schemas/form.v1.json` and is validated on save, both server and client.
- **Rules language** (`mawred.rule/v1`): a JSON AST of `all|any|not`, comparisons (`eq, neq, gt, gte, lt, lte, in, contains, empty, between`), refs (`field.x`, `requester.department`, `ctx.today`), and functions (`days_between`, `working_days_between(branch)`, `balance(leave_type)`, `sum`, `count`, `round`).
  - It is implemented in `resources/js/lib/rules` and `app/Support/Rules`.
  - Calculated fields use the same AST.
  - **Parity fixtures** in `tests/fixtures/rules/*.json` (input, context, expected) run in Pest and Vitest, and CI fails on drift.
- `buildZodSchema(schema, ctx)` (client) and `FormRulesBuilder::for(version)->validate(data, ctx)` (server) both start from the same schema. The server recomputes calculated and hidden fields and ignores client-sent values for them.
- **Versioning**: editing a published form creates a draft version. Publishing freezes it. Submissions pin `form_version_id`, and request types point to a form definition (latest published at submit time).
- **Files**: upload first to a temporary private media collection (signed, 24 h), and attach on submit. Mime sniffing, size limits and an optional ClamAV scan happen before the file is accepted.
- Public forms use a dedicated route group with throttling, **ALTCHA** self-hosted proof-of-work captcha (D10; `altcha-org/altcha` PHP + web component, HMAC key in the vault), a honeypot and a minimum fill time.

## Consequences
- + One source of truth, safe evaluation, testable parity.
- − We own a small expression engine. Its scope is deliberately limited, and growth happens only by adding typed functions.

## Implementation notes (Phase 3a)
- Rules: `app/Support/Rules/Rules.php` and `resources/js/lib/rules/index.ts`. Operators: `all any not eq neq gt gte lt lte in contains empty between add sub mul div round sum count if days_between working_days_between balance`. Null propagates; numeric strings compare as numbers; `round` is half away from zero in both. `balance()` reads `ctx.balances`, empty until the Phase 5 ledger.
- Visibility and calculated values share one algorithm (`FormResolution` / `resolveForm`, iterated until stable), with its own parity fixture (`tests/fixtures/forms/resolution.json`).
- The meta-schema `resources/schemas/form.v1.json` documents the contract; `FormSchema` enforces it on save (no JSON-Schema library was added). The builder relies on the server for structural errors and maps them back onto fields.
- `buildZodSchema` is one zod schema with a `superRefine` over the resolution, so hidden fields never block the client.
- Submissions are **encrypted at rest** (`data` as `encrypted:array`); opening one and downloading its files are audited (D54).
- Uploads: private `FormUpload`s that expire after 24 h unless claimed (pruned hourly), content type sniffed, size and kind per field, ClamAV via clamd when `CLAMAV_HOST` is set (fails closed).
- ALTCHA (D10): PBKDF2, cost 5000, counters 5000–10000 (about 3–4 s in Chromium), configurable in `services.altcha`; the widget starts on focus. The HMAC key is derived from `APP_KEY` until the credential vault ships (Phase 4); solved challenges are single-use.
- Request types pointing at forms, and submit-time `form_version_id` pinning through request types, come in 3b.
