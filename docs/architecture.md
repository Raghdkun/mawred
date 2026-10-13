# Mawred (مَورِد) — Architecture Plan

> Status: **DRAFT v0.2** (2026-10-13), updated with the interview decisions D1–D27 in [open-questions.md](open-questions.md). Waiting for approval; each ADR is in [adr/](adr/README.md); delivery order is in [roadmap.md](roadmap.md).

## 1. Scope in one paragraph

Mawred is an internal HR, hiring and workflow platform for **one legal entity** with an unlimited number of branches and employees. It is a Laravel 13 modular monolith. Two clients use it: an Inertia/React 19 web app, which is also an installable PWA, and later a Flutter app that uses a versioned REST API (`/api/v1`). Both clients call the same domain **Actions**. The core is a configurable **workflow engine**, a **form builder** and a **policy engine**. Every other module plugs into these three: requests, leave, scheduling, attendance, hiring, training, onboarding and offboarding.

## 2. Design inputs found in `/design`

| Brief says | Actually present | Plan |
|---|---|---|
| `/design/handoff.md` | ✅ `design/handoff.md`: 44 screens, global responsive/RTL/dark/motion rules, component inventory | The build spec per screen. Canvas exports (motion spec 07, illustrations, screen frames) go to `design/{motion,illustrations,screens}` (D28 ⏳). |
| `/design/tokens.css` | `design/tokens/mawred-tokens.css` (Tailwind v4 `@theme` + shadcn semantic vars, light/dark, status/health/chart palettes, motion tokens, reduced-motion, Arabic leading) | Import it as-is as the theme source of truth. Do not hand-edit it; local overrides go in `resources/css/theme-overrides.css`. |
| `/design/svg` | `design/icons/mw-*.svg` (20 module icons, 24px stroke, `currentColor`), `design/logo/{color,currentColor,dark,mono-blue,white-on-blue}/*` (symbol, micro symbol, AR/EN wordmarks, horizontal and stacked lockups), `design/app-assets/{svg,png}` (favicons, PWA icons including maskable, splash light/dark, AR/EN email and WhatsApp headers, AR/EN OG careers images) | Use the files as provided. Module icons become React components via SVGR, unmodified. Generic UI glyphs come from `lucide-react`, the shadcn default. |
| "illustration set" for empty/error/offline/denied states | exported from the canvas Illustrations page | ⏳ `design/illustrations/` (D28). |
| `manifest.webmanifest` | present (EN only, `#4570C0` theme) | Becomes the base for the vite-plugin-pwa manifest. AR name/short_name are added and `lang`/`dir` are switched per locale. |

Token facts that shape the code:
- Dark mode uses the `.dark` class on `<html>`, which matches the starter kit's appearance toggle.
- Status (`draft…returned`), integration health and the 8-colour chart palette are already tokens. Components must use them and never hard-code colour.
- Motion tokens: `--ease-soft` (data), `--ease-spring` (UI surfaces only), `--ease-exit`, and durations of 150/250/400 ms. The Motion library reads them through a small `motionTokens` TS module that mirrors the CSS values (a unit test checks both stay in sync).
- Arabic-Indic digits are a user preference: `data-digits="arab"` on `<html>` plus `Intl` with `-u-nu-arab`. Never mix digit systems in one view.

## 3. System context

```
                ┌────────────── Hostinger VPS (Ubuntu LTS, Docker Compose) ──────────────┐
 Browser/PWA ──►│ Caddy (TLS, HTTP/3, WS) ─► app (PHP)  ─┬─► PostgreSQL 17/18             │
 Flutter app ──►│        │                 ├ horizon     ├─► Redis (cache/queue/session) │
 Cognito ──────►│        └──── /app/* ───► reverb (WS)   ├─► Gotenberg (HTML→PDF)        │
 Meta WA Cloud ►│                          ├ scheduler   ├─► WAHA (unofficial WA, priv.) │
                │                          ├ ssr         ├─► ClamAV                      │
                │                          └ glitchtip   └─► (shares PG/Redis)           │
                └────────────────────────────────────────────────────────────────────────┘
                  ▲ outbound: SMTP provider, Meta Graph API, Hostinger Object Storage (backups), uptime ping
  hosts: hr.<domain> (app, API, WS) · careers.<domain> (public, SSR)
```

## 4. Codebase layout

```
app/
  Domain/
    Setup/ Organization/ People/ Access/ Policies/ Forms/ Workflows/ Requests/ Leave/
    Scheduling/ Attendance/ Calendar/ Hiring/ Training/ Reports/ Notifications/
    Integrations/ Audit/
      <Domain>/
        Models/        Eloquent models (+ casts, relations, local scopes)
        Actions/       one public `handle()` per class; the ONLY write path
        Data/          spatie/laravel-data DTOs (input + view models → TS types)
        Queries/       read models: list/search/report queries, always scope-aware
        Policies/      Laravel policies (delegate to Access\ScopeResolver)
        Events/ Listeners/ Jobs/
        Enums/ Exceptions/ Support/   (domain-local value objects, calculators)
        Contracts/     interfaces other domains may depend on
  Http/
    Controllers/Web/<Domain>/      Inertia controllers (thin)
    Controllers/Api/V1/<Domain>/   API controllers (thin)
    Controllers/Public/            careers, public forms, signed action links, webhooks
    Resources/Api/V1/              API Resources
    Middleware/
  Support/            shared kernel: Ulid, Money, LocalDate/BranchClock, Translatable helpers,
                      Idempotency, ApiEnvelope, SafeTemplate, Pdf (Gotenberg client), Rules engine
  Console/Commands/   mawred:install, mawred:reencrypt-credentials, partitions:maintain, …
resources/js/
  pages/<domain>/…            Inertia pages (route-level only)
  features/<domain>/…         components, hooks, schemas, api clients for that domain
  components/ui/              shadcn (generated, lightly themed)
  components/mw/              Mawred design-system parts (StatusBadge, HealthBadge, PageState, KPI, …)
  layouts/ lib/ i18n/ types/generated/ (laravel-data → TS)  routes/ (Wayfinder)
database/
  migrations/ (grouped by domain prefix) seeders/{Production,Demo,Performance}/
docs/ (this folder) • docker/ • deploy/ (scripts, runbooks) • tests/{Unit,Feature,Arch,Browser,Performance}
```

**Module rules** (enforced by Pest arch tests, see [ADR-001](adr/0001-modular-monolith.md)):
1. `Domain\*` never depends on `Http\*`, Inertia or request objects.
2. A domain may *read* another domain's models through relations or that domain's `Queries`. It *writes* to another domain only through that domain's `Actions`, or by reacting to its `Events`.
3. Controllers never call `Model::create/update/delete` or `DB::`. They authorize, build a Data object, call an Action and return a response.
4. Every list endpoint uses a `Queries\*` class that extends `ScopedQuery`.
5. Actions are `final`, have exactly one public method (`handle`), and run inside a transaction when they write.

## 5. Cross-cutting decisions (summaries; details in the ADRs)

### 5.1 Identifiers — [ADR-003](adr/0003-identifiers.md)
Internally every table has a `bigint` identity PK, which keeps foreign keys, joins and partitioned tables small and fast. Business tables also get a `ulid` column (unique, `char(26)`) that serves as the route key, the API id and the id in Inertia props. Internal ids never leave the server, and an arch test asserts that no Resource or Data class exposes `id`.

### 5.2 Time — [ADR-004](adr/0004-time-and-timezones.md)
- **Instants** (punches, approvals, created_at) are stored as `timestamptz` in UTC.
- **Calendar dates** (leave days, roster days, holidays, policy `effective_from`) are stored as `date` in the **branch-local** calendar. They are not instants and are never converted.
- A `BranchClock` service turns instant↔local date for a branch. Display uses the viewer's preference (their branch tz by default, overridable in their profile).
- Overnight shifts belong to the local date on which they **start**.
- Hijri display uses `Intl` with `islamic-umalqura` (both browser and PHP `IntlDateFormatter`). Hijri is display-only; storage is always Gregorian.

### 5.3 Access model — [ADR-005](adr/0005-authorization-and-scopes.md)
- spatie/laravel-permission holds **roles** and **permissions** named `module.action`.
- A `role_permission_scopes` table adds a **data scope** (`own < direct_reports < team_tree < department < branch < all`) to every role↔permission grant.
- **Role assignments can be anchored** to specific branches or departments (e.g. "HR Officer for Riyadh and Jeddah"). Without an anchor, the user's own placement (primary assignment) is used (D5). Users may exist without an employee record (external auditor, interviewer, bot); they get anchored or `assigned` scopes only (D6).
- `Access\ScopeResolver` is the only place that turns *(user, permission)* into a SQL constraint or a yes/no for a record. Policies, `ScopedQuery`, global search, report builder, exports and broadcast channel auth all call it.
- The reporting hierarchy uses a **closure table** (`employee_reporting_paths`), and the department tree uses PostgreSQL **`ltree`**. That makes `team_tree` and department-subtree checks a single indexed predicate, even with 5,000+ employees.
- **Field permissions** (`field_permissions`: role × field key → none/read/write) cover sensitive built-in fields and every custom field. Data objects resolve them through a `#[Sensitive('employee.salary')]` attribute. Each read is recorded, deduplicated per request, in the audit log.
- 2FA (Fortify TOTP) can be enforced per role. The rule is "any role requires it → user requires it", and middleware redirects to enrolment.
- Impersonation: Super Admin only, cannot impersonate another Super Admin, audited start and stop, persistent banner, and a session flag that blocks sensitive-field reads unless the impersonated user could read them anyway.
- "View as user" in the permission matrix is a **read-only preview**. It runs the ScopeResolver as the target user and shows counts and samples. It does not switch sessions.

### 5.4 Audit — [ADR-006](adr/0006-audit.md)
- spatie/laravel-activitylog records every model write through an `Auditable` trait. The trait logs dirty attributes and masks values of sensitive fields.
- Engine transitions, impersonation, credential changes, logins and out-of-app actions are written as explicit domain events.
- The `activity_log` table is range-partitioned by month (§5.12), carries a `request_id` per request or job (activitylog v5 dropped `batch_uuid`), and records the causer, the impersonator and the channel (`web|api|email-link|whatsapp|system`).

### 5.5 Sensitive data — [ADR-007](adr/0007-sensitive-data.md)
- Encrypted casts protect national ID, passport number, IBAN/bank details, salary, and any custom field flagged *sensitive*.
- Exact-match lookups (e.g. importing by national ID) use an HMAC **blind index** column keyed by a separate `BLIND_INDEX_KEY` env secret.
- Encrypted columns are never sortable or filterable, and the UI does not offer it.

### 5.6 Internationalisation & RTL — [ADR-011](adr/0011-i18n-rtl.md)
- Locales: **EN (default) + AR**, chosen per user (D3).
- UI strings use **react-i18next** with lazy namespaces per domain and ICU plurals (Arabic needs six plural forms).
- Server strings (mail, PDFs, validation, notifications) live in `lang/{ar,en}`.
- A single `lang/` tree generates both sides. A build step compiles `lang/*/ui/*.json` into the i18next resources, so keys are never duplicated.
- Content data (form labels, request types, job posts, templates, policy names) is stored in **translatable jsonb** columns (spatie/laravel-translatable). Admin UIs edit EN and AR side by side, with a missing-translation indicator.
- `<html lang dir>` is set server-side. Switching locale is an Inertia partial reload that loads the new namespaces, flips `dir`, and persists the choice to the user profile. There is no full page reload.
- Lint rule: physical classes (`ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left/right`, `rounded-l/r`, `border-l/r`, `float-left/right`) are errors.
- Directional icons use `rtl:-scale-x-100`. Motion slide directions read `dir` from context.

### 5.7 API — [ADR-012](adr/0012-api-design.md)
- `/api/v1`, Sanctum **tokens** for Flutter and integration bots, Sanctum **stateful cookie auth** for the web SPA's async widgets (pickers, search, infinite lists). The web app therefore reuses the API rather than duplicating JSON endpoints.
- Error envelope: `{ "error": { "code", "message", "details": {field: [..]}, "trace_id" } }`.
- Lists use cursor pagination plus spatie/laravel-query-builder with an allow-list of filters, sorts and includes.
- Every POST/PUT/PATCH/DELETE accepts `Idempotency-Key`. Responses are stored for 24 h, keyed by (token, key) together with a request hash.
- OpenAPI is generated by Scramble at `/docs/api`. Only API doc viewers can open it.

### 5.8 Forms — [ADR-008](adr/0008-form-schema.md)
- Mawred Form Schema (MFS) v1 is a JSON document validated by its own JSON-Schema meta-schema. It describes fields, layout, conditional logic, validation, defaults and calculations as **data**, never as code.
- One rules language serves form conditions, calculated fields and workflow conditions. A JSON AST with `all/any/not`, comparison operators, date and duration math and arithmetic is implemented once in TS and once in PHP. **Parity fixtures** (`tests/fixtures/rules/*.json`) run in both Pest and Vitest so the two implementations cannot drift.
- `buildZodSchema(schema, ctx)` gives client validation. `FormRulesBuilder` gives Laravel rules, plus a post-pass for conditional `required` and for calculated fields recomputed server-side, which is authoritative.
- Submissions pin `form_version_id`. Files go through medialibrary on a private disk.

### 5.9 Workflow engine — [ADR-009](adr/0009-workflow-engine.md)
Summarised in §6.

### 5.10 Policy engine — [ADR-010](adr/0010-policy-engine.md)
Summarised in §7.

### 5.11 Search — [ADR-013](adr/0013-search.md)
Global search (⌘K) runs on PostgreSQL full-text search (`simple` config, plus an Arabic-normalised `search_vector` that strips tashkeel and unifies alef, yaa and taa marbuta) and `pg_trgm` for fuzzy names and codes. Every search query goes through `ScopedQuery`, so it **cannot** return out-of-scope rows. There is no extra search engine to run or secure.

### 5.12 High-volume tables — [ADR-018](adr/0018-partitioning.md)
Native monthly range partitioning covers `attendance_events`, `activity_log`, `notification_deliveries`, `message_logs` and `webhook_receipts`. The PK is `(id, occurred_at)`. A scheduled `partitions:maintain` job creates partitions 3 months ahead and detaches or archives old ones according to the retention settings.

### 5.13 Integrations & secrets — [ADR-015](adr/0015-integrations.md)
- Integration credentials are stored in `integration_connections.credentials` with the `encrypted:array` cast and are masked in every Data object.
- Rotating `APP_KEY` uses Laravel's `APP_PREVIOUS_KEYS` plus `php artisan mawred:reencrypt` (credentials and every encrypted column, chunked, resumable).
- Infrastructure secrets (DB, Redis, APP_KEY, BLIND_INDEX_KEY, backup storage + backup archive password, GlitchTip DSN) live only in env.

### 5.14 Notifications — [ADR-016](adr/0016-notifications-templates.md)
- Delivery uses Laravel Notifications with channels `inapp` (database + Reverb broadcast), `mail`, `whatsapp` (through the router) and `webpush`.
- Templates are stored per *category × channel × locale* and rendered by a **logic-less** `SafeTemplate` (`{{ var }}` with escaping and an allow-listed variable map per category). Blade is never compiled from database content.
- Each user sets per-category channel preferences, quiet hours in their own timezone, and an optional daily digest.

### 5.15 PDFs — [ADR-014](adr/0014-pdf-gotenberg.md)
- Blade renders HTML with `dir`/`lang` and a self-hosted Readex Pro (base64 `@font-face`). The Gotenberg Chromium route turns it into a PDF.
- Generation is queued, and the result is stored as media on the private disk.
- Certificates, offer letters, attendance sheets and the PDF export of reports all go through this path.

### 5.16 Realtime
Reverb private channels:
- `users.{ulid}` for notifications, inbox counters and import progress
- `instances.{ulid}` for the live timeline
- `integrations.{ulid}` for the OpenWA QR stream and health
- `kiosk.{ulid}` for rotating QR codes

Channel authorization calls the same Policies.

### 5.17 Money
Multi-currency from day one (D2): `Money` = `amount_minor` + ISO 4217 currency, a default currency per branch, and no FX in v1. See [ADR-0022](adr/0022-money-and-currency.md).

### 5.18 Settings & feature flags
- A typed `settings` table, grouped (company, branding, locales, security, retention…), backs the setup wizard and Settings. Values are cached and the cache is invalidated on save.
- Pennant flags gate unfinished modules per role or user in production (e.g. `hiring`, `training`, `openwa`).

## 6. Workflow engine (core)

**Storage**
- `workflow_definitions` (key, translatable name, category)
- `workflow_versions` (graph `jsonb`, `status` = draft/published/archived, checksum, published_by/at)
- `workflow_instances` (pinned `version_id`, morph `subject`, `status`, `context` jsonb variables, `requester_id`, `correlation_key`)
- `workflow_steps` (one row per node activation: `node_id`, `iteration`, `status` = pending/running/waiting/completed/failed/skipped/cancelled, `attempts`, `available_at`, `due_at`, `input`, `output`, `error`)
- `workflow_tasks` (human work for Approval and Assign Task: assignee, original_assignee, delegated_from, decision, comment, decided_via channel)
- `workflow_timeline` (append-only, human-readable)
- `action_tokens` (out-of-app approvals: sha256 of the token, task, decision, channel, `expires_at`, `used_at`)

**Execution model**
- A deterministic token-passing state machine.
- `WorkflowEngine::advance($instance)` takes `Cache::lock("wf:{$id}")` and `SELECT … FOR UPDATE` on the instance, finds runnable steps and dispatches `RunStep` jobs on the `workflows` Horizon queue.
- Each node type has one `NodeHandler` class: `handle(StepContext): NodeResult`, where the result is `Completed(outputs, edges)`, `Waiting(until|task)` or `Failed(reason, retryable)`.
- Handlers are **idempotent**. Side effects carry the step ULID as an idempotency key (unique constraints on ledger entries, outgoing webhooks, created records), so a retry cannot double-post.
- Retries use exponential backoff (`[10, 60, 300, 900]` s). After that the step is `failed` and appears in the **dead-letter view**, which offers retry, skip-with-output and cancel-instance, all audited.
- **Join** waits until every inbound branch from its matching split is completed or skipped. Split and join pairing is validated when a version is published.
- **Return for changes** reopens the submission for the requester, and resubmitting re-enters the approval node with `iteration + 1`. The timeline keeps every round.
- **Timers** (Delay, Wait-until, SLA, reminders): DB rows with `available_at` / `due_at` are the source of truth. A minutely `workflows:tick` sweeper wakes due steps. Delayed jobs are only an optimisation; the system stays correct if Redis is flushed.

**Publishing** runs validation (exactly one start, every node reachable, all paths end, split and join balanced, required config present, assignee resolvers valid, rule ASTs type-check against the bound form schema). Running instances keep their pinned version.

**Assignee resolvers** are each a strategy class: user, role, role-within-scope (resolved relative to the requester's org placement), direct manager, manager chain level N, department head, branch manager, user from a form field, and round-robin in a group (cursor kept in Redis with a DB fallback). Every resolver has a deterministic fallback: if nobody resolves, the task escalates to a configurable fallback role and the problem is flagged on the instance.

**Delegation**
- A `delegations` table holds from, to, period, and optionally specific definitions.
- Automatic delegation applies while the approver has approved leave covering "now" and a default delegate is set. It is resolved when a task is created and again on escalation.

**Out-of-app actions**
- Approve/reject links are single-use, expiring and hashed.
- Opening the link shows a confirmation page, which works without login but is bound to the assignee. Confirming records `decided_via = email|whatsapp`.
- WhatsApp Official interactive buttons carry an opaque token id in the button payload. OpenWA sends the signed link instead.
- A token is invalidated when the task is decided by any channel.

**Simulator**
- Runs the same engine with an in-memory repository and a `SimulationMode` that swaps Notify, Webhook and Update Record for recorders.
- Takes sample form data and a requester, and returns the path, the resolved assignees per node and any validation errors.
- Lets the builder pick decisions at approval nodes to explore different branches.

## 7. Policy engine

- **Key registry**: each configurable rule is a PHP `PolicyKey` class with an id (e.g. `attendance.grace_minutes`, `leave.annual.accrual`, `overtime.multipliers`), a typed value schema (laravel-data), a UI editor hint, and a default. **There is no free-text formula anywhere.** Accrual and overtime are compositions of typed components: period, rate, pro-rata method, rounding, caps, and day-type multipliers for weekday, weekend, holiday and night.
- **Layered partial sets**: a `policy_set` defines any subset of keys. `policy_assignments` attach sets to a target level: `global < branch < department < position < grade < employment_type < employee` (employment type beats grade, D13).
- **Resolution** is per key. For an employee on a date, the value comes from the highest-priority assignment that defines the key, which also gives the explainer its provenance chain.
- **Effective dating**: values (`policy_values`) and assignments carry `effective_from` / `effective_to` as local dates. Edits create new rows and never modify past ones. "Resolve at date D" is a pure function.
- **Ledgers**:
  - `leave_ledger_entries` (accrual, usage, adjustment, carry-over, expiry, reversal; quantity in the leave type's unit, which is days with half-days, or hours)
  - `hourly_permission_ledger`
  - attendance results recomputed from raw events
  - Balances are a projection (`leave_balances`) that can be rebuilt from the ledger at any time.
- **Recalculation**: system-generated ledger entries carry `calc_run_id` and `source_rule`. When a policy changes effective from D, a recalculation run computes what the entries *should* be from D onward with the pure calculators. It diffs against what exists and posts **reversal plus new entries**, never updates.
- **Impact preview** is the same diff, unposted. It lists affected employees with balance deltas and attendance metric deltas before the admin confirms. Runs are queued and chunked by employee.
- **Holidays** (D12): per-branch calendars with `fixed`, `nth_weekday` and `one_off` rules, observed-date shifting, CSV import, and optional Hijri **display**. There are no Hijri-recurring rules; Islamic holidays are one-off entries. A "US Federal holidays" preset ships.
- **Presets** export and import as versioned JSON (`mawred.policy-preset/v1`). The default preset is neutral and clearly labelled.

## 8. Other domains (key models & notes)

- **Organization**
  - Models: `branches` (tz, weekend days, geofence lat/lng/radius, holiday calendar, policy assignment), `departments` (ltree path, head), `positions`, `employment_types`, `grades`, `cost_centers`.
  - The org chart is lazy-loaded by subtree. Pickers use async API search.
- **People**
  - Models: `employees` (one row per person employed; `user_id` nullable until activation; one user ↔ at most one employee), `employee_assignments` (effective-dated job placement: branch, department, position, manager, type, grade; exactly one **primary** at a time plus optional **secondary** ones for rostering and visibility, D25), `custom_field_definitions` with typed values in `employee_custom_values`, documents via medialibrary with an `expires_on` reminder job, `employment_history` with restricted fields, `asset_assignments`.
  - Import wizard: upload → map → validate preview → dry run → commit. It is chunked and queued, and the error report is an XLSX.
  - Activation invites go by email or WhatsApp.
  - Self-service profile edits create a `profile_change_request` that runs through the workflow.
- **Requests**
  - `request_types` (translatable, icon, category, `form_version` *or* `cognito_source`, workflow definition, eligibility rules)
  - `requests` (reference no. `REQ-YYYY-NNNNNN`, requester, submission, instance, status mirror)
  - The request center lists native and Cognito types identically. The approvals inbox lists `workflow_tasks` with bulk actions.
- **Leave**
  - A ledger driven entirely by the policy engine.
  - Requests are validated for overlap, blackout periods, balance, and the sick-leave attachment threshold.
  - A year-end job handles carry-over and expiry.
- **Scheduling**
  - Models: `shift_templates`, `rotation_patterns`, `rosters` (branch × period, draft/published), `roster_shifts`, `open_shifts`, `coverage_targets`.
  - `ConflictDetector` checks overlap, leave, rest hours, weekly max (all from policy) and skill/position fit.
  - The grid is virtualised (TanStack Virtual, both axes).
  - Shift swaps and open-shift claims run through workflows.
- **Attendance**
  - `attendance_events` (partitioned; source pwa/kiosk/correction/import; server-received time vs device time; geo + accuracy)
  - `attendance_days` (computed per employee per local date)
  - The kiosk shows rotating HMAC-signed QR codes (TTL ~20 s) over Reverb, and the employee scans them from the PWA.
  - The offline queue lives in IndexedDB, and the server checks the device-time drift budget.
  - Geofence enforcement is a branch policy key, `attendance.geofence_mode`: `block`, `flag` (default) or `off`. Flagged punches need a reason and show on the daily board for the manager to accept or reject (D26).
  - The monthly **payroll-input export** (overtime by multiplier, unpaid leave, lateness minutes, allowance changes; per branch and currency) gets its data here and ships as a report in Phase 8 (D7).
- **Calendar**
  - Models: `calendar_events` (meeting/interview/session), `event_participants`.
  - The availability finder merges shifts, leave, events and working hours.
  - Booking pages for candidates.
  - `.ics` attachments.
  - The provider sits behind `CalendarProvider` (only the internal one in v1).
- **Hiring**
  - Models: `job_requisitions` (created by workflow), `job_postings` (translatable, slug, SEO/OG), `candidates` (dedupe on normalised email and E.164 phone), `applications`, `pipeline_stages` (per job, cloned from a template), `screening_questions` (knock-outs), `scorecards`, `interviews`, `offers` (PDF, workflow, e-accept by signed link), `consents`, a retention purge job.
  - Hire → `CreateEmployeeFromCandidate` → onboarding workflow.
  - Interviewers are restricted by an `assigned` scope.
- **Training**
  - Models: `courses`, `learning_paths`, `sessions` (capacity, waitlist), `enrollments` (rule-based auto-assign), `quizzes` and `attempts`, `feedback`, `certificates` (PDF, QR → public `/verify/{code}`), expiry reminders.
- **Reports**
  - Prebuilt reports are `Queries` classes.
  - The custom builder works from a **dataset registry**: each dataset declares columns, filters and groupings, its scope mapping and its sensitive fields. It never accepts raw SQL from users.
  - Runs are queued, produce XLSX/CSV (openspout) or PDF (Gotenberg), and send a "ready" notification. Report schedules are saved and shared by role.
- **Setup**
  - The wizard state lives in `settings`. Steps can be skipped or revisited.
  - `EnsureSetupComplete` middleware shows a friendly page to non-admins.
  - Bootstrap: `php artisan mawred:install` creates the first Super Admin and prints a one-time setup link valid for 24 h. Step 6 sets the password, enforces 2FA and invites admins (D4).
  - Branding (D22): the company logo and accent colour appear only in co-brand areas (login, email header strip, PDF letterhead, careers site). The app UI always uses the Mawred tokens.

## 9. Frontend architecture

**Handoff layout rules** (from `design/handoff.md`):
- **Breakpoints**: Tailwind defaults line up with the handoff. `xl` (1280) collapses the sidebar from 260 px to 72 px, and below `md` (768) a bottom tab bar (Home, Requests, New FAB, Schedule, Me) and a top app bar take over.
- **Grid**: 12 columns, 24 px gutter, 1280 px max on desktop; 8 columns on tablet.
- **Detail views**:
  - Desktop: a 440 px drawer from the reading end.
  - Tablet: full-height 560 px drawers.
  - Mobile: bottom sheets (snap 50%/92%) replace drawers and popovers.
- **Desktop-only screens (1280+)**: form builder, workflow builder, custom report builder and the permission matrix. On mobile, the import wizard and policy editor show a "continue/edit on desktop" card or a read-only summary.
- **Home**: one route with three role-based layouts (employee, manager, HR admin). The user's highest role picks the layout, with a switcher for people who hold several roles.
- **Mirroring**:
  - Mirror: arrows, chevrons, breadcrumbs, progress, sliders, steppers, drawers, swipe, drag tilt (−1.5° in AR), page-enter slide, the `mw-requests`, `mw-workflows` and `mw-interviews` icons, and the Returned status glyph.
  - Never mirror (wrap in `dir="ltr"`): checkmarks, clocks, media, search, charts, logos, map pins, phone numbers, IDs, IBANs, URLs, code, OTP cells, JSON viewers, `{{var}}` chips.
- **Dark mode**: app-wide from the tokens. Launch visual QA covers the 9 ★ screens (D32).


- Stack: the Inertia + React 19 + TS starter kit, Wayfinder for typed routes, shadcn/ui on tokens.css, and Readex Pro (self-hosted variable woff2, subsetted to Arabic + Latin + digits/punctuation).
- **Shared types**: laravel-data plus the TypeScript transformer generate `types/generated/*.d.ts`. CI fails if the generated output differs from the committed copy.
- **State**: page data comes from Inertia props, using deferred props for heavy panels. TanStack Query handles async API widgets (pickers, search, infinite lists). react-hook-form + zod handles forms. Server validation errors map back onto RHF fields.
- **App shell**:
  - A sidebar with module icons (`mw-*.svg`), turning into a bottom tab bar under 768 px.
  - An async branch switcher.
  - A ⌘K palette (cmdk) for navigation, quick actions and global search.
  - A notification center fed by Reverb.
  - An impersonation banner.
  - Sonner toasts, positioned according to `dir`.
- **Responsive primitives**: `<ResponsiveDialog>` (dialog ↔ bottom sheet), `<DataView>` (TanStack Table ↔ card list under `md`), and touch targets ≥ 44 px through the size scale.
- **Page states**: `<PageState kind="empty|loading|error|offline|denied">`, using the illustration set (⏳ D1) and skeletons with `animate-shimmer`.
- **Motion**: Motion presets mapped to the tokens (ripple, approval stamp, integration-connected, count-up KPI, page transitions), all wrapped in `useReducedMotion`.
- **Builders**:
  - The form builder uses dnd-kit with an inspector panel and an EN/AR/phone preview powered by the real renderer.
  - The workflow canvas uses @xyflow/react with a typed node registry, a validation overlay and a simulator panel.
  - The roster grid uses virtualisation with dnd-kit.
- **PWA**: vite-plugin-pwa (`injectManifest`, a custom SW for push and background sync). Only the app shell, static assets and the offline page are cached. **HR data responses are never cached** (shared-device risk). The attendance queue and drafts live in IndexedDB.

## 10. Quality gates

Pint, Rector (dry-run in CI), Larastan level max with a committed baseline that must only shrink, ESLint 10 (flat config, logical-classes rule, jsx-a11y, i18next no-literal-string) plus Prettier (D17; vite-plus removed), Pest 5 (D16), `tsc --noEmit`, Vitest, Pest (Unit, Feature, Arch, Browser), axe on every browser test, a composer/npm audit, the generated-types diff, and the build. Performance tests run nightly against the performance seed. Nothing merges red. See [testing strategy in roadmap](roadmap.md#testing-strategy).

## 11. Performance budgets and how they're met

| Budget | Mechanism |
|---|---|
| List pages < 300 ms server time | Cursor pagination, composite indexes per declared filter/sort (each `Queries` class documents its index), closure/ltree for scope predicates, no `count(*)` on huge lists (estimated or deferred counts), `preventLazyLoading` + `preventAccessingMissingAttributes` outside production, Pulse slow-query recorder |
| Roster week, 200 employees | One query per roster week; a flat normalised payload (employees[], shifts[] keyed by employee/date); a 2-axis virtualised grid; memoised cells; drag state kept outside React render |
| No N+1 | `Model::shouldBeStrict()` in non-prod, plus a query-count assertion helper in feature tests (`expectQueryCount(<= n)`) |
| Heavy reports and exports | Always queued (Horizon `reports` queue, its own supervisor) with a ready notification |

## 12. Deployment (summary — [ADR-017](adr/0017-runtime-and-proxy.md), [ADR-019](adr/0019-deployment.md))

- Single Ubuntu LTS VPS running Docker Compose. Caddy is the edge (automatic Let's Encrypt, WebSocket proxy to Reverb).
- One PHP image runs the app, horizon, scheduler and reverb.
- GitHub Actions builds and tests the images, pushes them to GHCR, and deploys over SSH with a blue/green switch of the `app` service behind Caddy. The sequence is: migrate (expand-only), warm caches, `horizon:terminate`, health check, then switch or roll back.
- Nightly encrypted spatie/laravel-backup to Hostinger Object Storage (D15), plus a monthly restore drill runbook.
- KVM 4 (4 vCPU / 16 GB) with ClamAV on (D20). GlitchTip self-hosted plus an external uptime ping (D21).
- Hardening script: deploy user, SSH keys only, UFW 22/80/443, fail2ban, unattended-upgrades, swap, Docker log rotation, UTC.
