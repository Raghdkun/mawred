# Mawred — Phased Roadmap

> DRAFT v0.1. Every phase ends with **green CI, a demo script (`docs/demos/phase-N.md`), updated docs and ADR statuses**, plus visual checks of every new screen at 390/834/1440 px in LTR and RTL, light and dark.
> **API (D27)**: each phase ships the `/api/v1` endpoints for its employee- and approver-facing features, plus the endpoints the web async widgets use. Admin builders stay web-only.
> Sizes are relative: S ≈ 1 week, M ≈ 2–3 weeks, L ≈ 4–6 weeks for one engineer working with AI assistance. They are planning signals, not commitments.

## Phase 0: Foundation (L) ✅ built, pending CI on GitHub (Actions account issue) and owner review
> Demo: [docs/demos/phase-0.md](demos/phase-0.md). Deviations: D34 (ESLint 9), D35 (org scopes fail closed until Phase 1), D36 (audit export moves to Phase 8).

## Phase 0: original scope

**Scaffold & tooling**
- `laravel new` with the React starter kit, in a temp dir and then merged into this repo, because `/design`, `/docs` and `CLAUDE.md` already exist. Then `git init` and the private remote `Raghdkun/mawred` (D14).
- Install and configure Laravel Boost, Sanctum, Horizon, Reverb, Pennant, Pulse, Scramble and Sentry. Pin the versions listed in [stack-versions.md](stack-versions.md).
- Pest 5 + PHPUnit 13 with arch presets and the browser plugin; Larastan at max with a baseline; Rector (L13 set); Pint; ESLint 10 + Prettier replacing vite-plus; Vitest; axe helper.
- Local dev is hybrid (D23): native PHP (upgrade to the latest 8.4.x) + Node, with Docker Compose services `pg18`, `redis`, `gotenberg`, `mailpit`, `waha`, `clamav`.
- GitHub Actions: lint → types → static analysis → tests (PG 18 + Redis services, parallel) → browser tests → build. Nightly perf job (stubbed for now).
- Docker: dev `compose.yaml` (services only) and production Dockerfile (FrankenPHP).

**App shell & design system**
- tokens.css wired as the Tailwind theme, shadcn re-themed, Readex Pro self-hosted, dark mode.
- react-i18next with an EN/AR key-parity check. RTL `dir` switching without reload. Logical-class lint rule.
- App shell: sidebar → bottom tabs, branch-switcher stub, ⌘K skeleton, notification-center skeleton.
- `PageState` (empty/loading/error/offline/denied), skeletons, motion presets, toasts.

**Platform**
- Auth: Fortify login, password reset, TOTP 2FA, role-enforced 2FA middleware, active sessions, login history.
- Access: roles and permissions seeded, `role_permission_scopes`, anchors, `ScopeResolver` + `ScopedQuery`, field permissions, permission matrix UI (search, clone role, view-as preview), impersonation + banner.
- Audit: activitylog configured, `Auditable` trait, audit viewer.
- Shared kernel: `HasPublicUlid`, `BranchClock`, `Money` (amount_minor + currency, D2), API envelope and exception mapping, idempotency middleware, `SafeTemplate`, settings store, partition maintenance command.
- Setup wizard skeleton: `mawred:install`, wizard frame with resumable/skippable steps 1–3 and 10; steps 4–9 are placeholders until their phase. "Being set up" page.

**Exit criteria**
- Arch tests are live: module rules, authorized routes, no internal ids in Resources.
- Scope-leak harness exists with a sample endpoint.
- The shell and the auth screens pass axe and the visual checks in all 12 variants.

## Phase 1 — Organization & People (L) ✅ built, pending CI on GitHub (Actions account issue) and owner review
> Demo: [docs/demos/phase-1.md](demos/phase-1.md). Decisions: D37–D45. Exit criteria met locally: 5,000-employee lists answer in < 10 ms with 4–6 queries for every scope shape; scope-leak tests cover all six scopes; a 10,000-row import validates in ~6 s and commits in ~3 min (`composer test:performance`).

- Branches (tz, weekend, working hours, geofence map picker), departments (ltree, unlimited depth), positions, employment types, grades, cost centers. Setup wizard step 4.
- Employees with effective-dated assignments, managers (direct, department head, branch manager), reporting closure table, lazy org chart.
- Custom fields (typed, permissioned), sensitive fields (encryption + blind index), documents with expiry reminders, employment history, assets.
- Bulk import wizard (OpenSpout, chunked, dry run, error report), and activation invites (email now, WhatsApp in Phase 4).
- Global search v1 (employees). Async pickers via `/api/v1`.
- Demo seeder (org + ~120 people), performance seeder (50 branches / 5,000 employees).
- **Exit**: employee list < 300 ms on the performance seed; scope-leak tests across all six scopes; import of 10k rows passes in CI-sized form.

## Phase 2 — Policy engine (M–L) ✅ built, pending CI on GitHub (Actions account issue) and owner review
> Demo: [docs/demos/phase-2.md](demos/phase-2.md). Decisions: D46 (branch work week is the default), D47 (shared holiday calendars), D48 (leave type catalogue now), D49 (neutral default values), D50 (calculator semantics). Balance deltas in the impact preview arrive with leave ledgers in Phase 5.
- Key registry, policy sets, layered assignments, effective dating, resolver + explainer UI.
- Holiday calendars (`fixed`, `nth_weekday`, `one_off`, observed-date shifting, CSV, Hijri display only). Presets: import/export, the neutral default, and the US Federal holiday preset (D12). Setup wizard step 5.
- Calculators (accrual, overtime, attendance metrics) as pure functions, plus the recalculation and impact-preview framework (wired to the ledgers in Phase 5).
- **Exit**: precedence and effective-dating test matrix; explainer shows provenance; preset round-trip.

## Phase 3 — Forms, workflows & requests (XL, split 3a/3b) — 3a ✅ and 3b ✅ built (pending CI and owner review)
> Demos: [phase-3a.md](demos/phase-3a.md), [phase-3b.md](demos/phase-3b.md). Decisions: D51 (core field types), D52 (dnd-kit, React Flow, ALTCHA), D53 (general public forms), D54 (forms implementation choices), D55 (seeded request types), D56 (default approval chain), D57 (3b implementation choices).
- **3a**: MFS v1 + rules engine with parity fixtures; form builder (all field types, logic, multi-page, EN/AR + phone preview); renderer; public-form hardening.
- **3b**:
  - Workflow engine: all node handlers, assignee resolvers, delegation, SLA, reminders, dead-letter view, timeline, simulator; React Flow canvas.
  - Request types and the request center, approvals inbox (bulk actions), out-of-app email approvals (signed, single-use).
  - Notifications (in-app via Reverb + email, preferences, quiet hours, digest), email templates, email setup step (wizard step 7).
  - Seeded request types: forms and workflows for all 15. Ones that depend on later modules (leave balance, shift swap, attendance correction, training, job requisition) get their **Update Record** handlers in the phase that owns the target domain, and are feature-flagged until then.
  - Onboarding/offboarding checklists; self-service profile edit through a workflow.
- **Exit**: scenario tests per seeded type (approve, reject, return, escalate, delegate, cancel, parallel); the "submit day off → approve from inbox" browser journey in EN and AR.

## Phase 4 — Integrations (L) — ✅ built (pending CI and owner review)
> Demo: [phase-4.md](demos/phase-4.md). Decisions: D58 (verify numbers first), D59 (Official → Email), D60 (Cognito forms open in a new tab, prefilled), D61 (unmatched entries wait for an admin), D62 (Phase 4 implementation choices).
- Credential vault, `mawred:reencrypt`, integrations hub with health badges, health job and alerts.
- Cognito sources: three event URLs, raw receipts, idempotency, field-mapping UI with sample capture, replay and error view, request-center parity. Setup wizard step 9.
- WhatsApp wizard: Official (webhook verify, signatures, templates sync, 24 h window logic, quick-reply approvals); unofficial sidecar (QR over Reverb, health, re-pair); routing with fallback chains and the critical-category warning. Setup wizard step 8.
- Signed approval links over WhatsApp; WhatsApp activation invites.
- **Exit**: faked Cognito submit/update/delete/duplicate tests; signature valid/invalid tests; sidecar fake; routing/fallback tests; wizard step tests.

## Phase 5 — Time (XL, split 5a / 5b-1 / 5b-2) — ✅ built (pending CI and owner review)
> Demos: [phase-5a.md](demos/phase-5a.md), [phase-5b-1.md](demos/phase-5b-1.md), [phase-5b-2.md](demos/phase-5b-2.md). Decisions: D63 (calendar leave year), D64 (reserve, then deduct), D65 (overdraw blocked), D66 (5a then 5b), D67 (leave engine choices), D68 (policy hours without a shift), D69 (overtime computed then approved), D70 (PWA + QR packages), D71 (5b in two PRs), D72 (swaps), D73 (open shifts), D74 (roster and attendance choices), D75 (meeting replies), D76 (calendar privacy), D77 (booking), D78 (repeats), D79 (calendar choices).
- **5a**:
  - Leave engine: ledger, accrual jobs, half-day/hourly units, hourly-permission quota, overlap and blackout checks, sick-leave attachment rule, year-end carry-over/expiry, recalculation and impact preview wired end-to-end.
  - Leave calendar (team/department/branch).
- **5b**:
  - Shift templates, rotations, roster planner (virtualised grid, DnD, copy week, apply pattern, open shifts, coverage heatmap), conflict detection, publish flow, swaps and claims through workflows.
  - Attendance: PWA clock-in with geofence, QR kiosk, offline queue, calculations, daily board, corrections through workflows, monthly sheet export.
  - Internal calendar: meetings, availability finder, booking slots, `.ics`.
- **Exit**: perf (roster week for 200 employees renders smoothly, attendance on 12 months of seed data); clock-in and publish-roster browser journeys; "edit policy with impact preview" journey.

## Phase 6 — Hiring (L, split 6a / 6b, D80) — 6a ✅ 6b ✅ built (pending CI and owner review)
- **6a**: requisitions with their approval chain, hiring team and pipeline; bilingual postings with knock-outs; the server-rendered careers site (sitemap, JSON-LD, OG); applications with duplicate detection; the pipeline kanban; candidate profile with CV preview; tags and talent pool.
- **6b**: interviews and candidate self-booking, scorecards, offers (PDF, approval, e-accept), hire → employee + onboarding, stage automations, comms log, retention purge, interviewer scoping, Cognito-backed postings.
- Requisition workflow → postings (bilingual, SEO/OG, SSR careers page) → native or Cognito applications → stages, screening knock-outs, scorecards, interviews with candidate self-booking, offers (PDF + approval + e-accept), hire → employee + onboarding.
- Kanban, automations on stage change, duplicate detection, tags and talent pool, comms log, CV preview, consent and retention purge, interviewer scoping.
- **Exit**: "move candidate" journey; interviewer scope-leak tests.

## Phase 7 — Training (M)
- Catalog, learning paths, sessions (capacity, waitlist, attendance), enrollment rules plus an optional approval workflow, quizzes, feedback, certificates (PDF + QR verify page) with expiry reminders, training needs requests, per-employee training record.

## Phase 8 — Reports & dashboards (L)
- Prebuilt reports (all in brief §16) plus the **monthly payroll-input export** (D7), dataset registry, custom builder (dataset → columns → filters → grouping → chart), saved/shared/scheduled reports, XLSX/CSV/PDF exports (queued), role dashboards with count-up KPIs.
- **Exit**: every report has a scope-leak test, and heavy reports are queued.

## Phase 9 — Production readiness (M)
- PWA hardening (push, background sync, install prompt, update toast), perf pass against the budgets, security review (headers/CSP, upload scanning, rate limits, dependency audit), WCAG 2.2 AA audit.
- Hostinger provisioning: harden script, Compose prod, Caddy, backups offsite + first restore drill, monitoring and alerts, CI/CD zero-downtime deploy with rollback.
- Runbooks: backup/restore, WhatsApp token rotation, OpenWA re-pair, APP_KEY rotation, scale-out (separate DB, S3).

## Testing strategy

| Layer | Tooling | Runs |
|---|---|---|
| Unit | Pest: calculators, rules engine, node handlers, resolvers | every PR |
| Feature | Pest: every Action, Policy, API endpoint; scope-leak datasets; query-count assertions | every PR |
| Arch | Pest arch: module boundaries, authorized routes, no internal ids exposed, Actions shape | every PR |
| Parity | Shared JSON fixtures: rules engine (PHP ⇄ TS) and form validation (zod ⇄ Laravel) | every PR |
| Browser | Pest browser (Playwright), EN + AR, axe assertions | every PR (critical journeys), full suite nightly |
| Frontend | Vitest + Testing Library: form renderer, roster grid, canvas serialisation | every PR |
| Performance | Pest perf suite on the performance seed with server-time budgets | nightly + before release |
| Visual | Playwright / Chrome DevTools MCP: 390/834/1440 × LTR/RTL × light/dark | per screen, at end of phase |

## Handoff screen → phase map

| Phase | Handoff screens |
|---|---|
| 0 | 1 Login ★, 1a 2FA, 1b Forgot password, 1c First-login onboarding, 2 Setup wizard (frame + steps 1–3, 6, 10), 3 App shell, 38 Roles & permissions matrix, 43 Audit log, 44 Notification center (shell + preferences) |
| 1 | 7 Employee directory, 8 Employee profile, 9 Bulk import, 39 Organization, setup step 4 (map) |
| 2 | 16 Policy sets, 17 Policy editor, 18 Assignment & precedence, 19 Effective policy, 20 Change-impact preview (framework), 21 Holiday calendar, setup step 5 |
| 3 | 10 Request center, 11 Request form/review/submitted, 12 My requests, 13 Approvals inbox ★, 14 Form builder ★, 15 Workflow builder ★, 42 Notification templates (email), 4 Employee home ★ (v1), 5 Manager home, setup step 7 |
| 4 | 40 WhatsApp wizard, 41 Integrations hub + Cognito, 42 (WhatsApp tab), setup steps 8–9 |
| 5 | 20 (wired to ledgers), 22 Roster planner ★, 23 My schedule, 24 Leave calendar, 25 Attendance check-in ★, 26 Attendance admin, 27 Meetings & interview scheduler, 4 (clock-in + balances) |
| 6 | 28 Job requisitions, 29 Pipeline kanban ★, 30 Candidate profile, 31 Careers/apply, 32 Offer letter |
| 7 | 33 Catalog, 34 My learning/quiz, 35 Certificate + verification |
| 8 | 36 Reports library/viewer, 37 Custom report builder, 6 HR admin home ★ |

**Visual QA per screen**: 390 / 834 / 1440 × EN-LTR / AR-RTL in light mode, plus dark for ★ screens (D32). Desktop-only screens are checked at 1440 (and 834 for the read-only fallback); at 390 only the "continue on desktop" card is checked.
