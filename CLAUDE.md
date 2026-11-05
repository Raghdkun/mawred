# Mawred (مَورِد) — project conventions for Claude

An internal HR, hiring and workflow platform for one legal entity with unlimited branches. Laravel 13 modular monolith + Inertia v3 / React 19 / TS / Tailwind 4 / shadcn. Bilingual AR/EN, RTL-first.

Laravel Boost guidelines (generated, git-ignored; run `php artisan boost:install` after cloning): @AGENTS.md

**Read first**: [docs/architecture.md](docs/architecture.md) · [docs/adr/](docs/adr/README.md) · [docs/roadmap.md](docs/roadmap.md) · [docs/stack-versions.md](docs/stack-versions.md) · [docs/open-questions.md](docs/open-questions.md) (decision log D1–D54)

## Ground rules
- **Plan before code** for any non-trivial change. Ask before any decision the brief or ADRs leave ambiguous. Record new decisions as ADRs.
- Check library APIs with **Laravel Boost** (`search-docs`, schema, tinker, logs, browser logs) and **Context7**. Never rely on memory: this stack (Inertia v3, Vite 8, TanStack Table v9, Motion 14, Pest 5) has moved recently.
- Locales: EN (default) + AR. Money is always `amount_minor` + currency (no FX). Company branding appears only in co-brand areas; the app UI always uses Mawred tokens.
- Don't add a composer or npm dependency without checking L13 / React 19 compatibility and noting it in `docs/stack-versions.md`.
- A feature is **done** only when it has: Actions + Policies + feature tests (including scope-leak tests), the API endpoint if applicable, EN + AR strings, all page states, RTL and dark mode verified, and browser tests for critical journeys. See "Definition of done" below.

## Backend conventions
- Code lives in `app/Domain/<Domain>/{Models,Actions,Data,Queries,Policies,Events,Listeners,Jobs,Enums,Exceptions,Support,Contracts}`. HTTP code lives in `app/Http/Controllers/{Web,Api/V1,Public}`. The shared kernel is `app/Support`.
- **Actions** are the only write path: `final class VerbNoun { public function handle(...) }`, constructor DI, `DB::transaction` when writing, domain events after commit, audit with an `Actor`. Inertia controllers, API controllers, jobs, workflow nodes and webhooks all call the same Action.
- **Controllers stay thin**: authorize (policy) → Data object → Action → Inertia response or API Resource. No `DB::` and no `Model::create/update` in controllers.
- **Reads**: list, search, report and export queries are `Queries\*` classes extending `ScopedQuery`. They always go through `Access\ScopeResolver`. Never hand-roll visibility `where`s.
- **IDs**: bigint `id` internally; `ulid` is the route key and the only id that is serialised (Resources and Data expose `id` = ULID). Never leak the internal `id`.
- **Time**: instants are `timestampTz` in UTC. Calendar concepts (leave day, roster date, holiday, effective_from) are `date`, branch-local. Use `BranchClock`. Never use `now()->toDateString()` in domain code.
- **Translatable content**: jsonb columns via spatie/laravel-translatable. UI and server strings live in `lang/{en,ar}`, and both locales must stay key-complete.
- **Sensitive fields**: encrypted casts + `#[Sensitive('key')]` on Data properties + field permissions. Blind index for exact lookups. Never log the values.
- **Audit**: business models use `Auditable`. State transitions, sensitive reads, exports and credential changes are audited explicitly.
- **Templates from the DB** render only through `SafeTemplate`. Never compile Blade from DB content.
- **No free-text formulas.** Use the typed rules AST (`mawred.rule/v1`) and the policy key components.
- **Migrations**: `YYYY_MM_DD_HHMMSS_<domain>_<what>`. Expand-only, so the previous release keeps working. Add composite indexes for every declared filter and sort. Partitioned tables use raw SQL (ADR-0018).
- **Jobs**: idempotent, with explicit queue (`default`, `workflows`, `notifications`, `imports`, `reports`, `pdf`, `integrations`), `#[Tries]` / `#[Backoff]`, and unique where duplicates would matter.
- **Strictness**: `Model::shouldBeStrict()` outside production. Feature tests assert query counts on list endpoints.
- **API**: `/api/v1`, API Resources, error envelope `{error:{code,message,details,trace_id}}`, cursor pagination, `Idempotency-Key` on mutations, and 404 (not 403) for out-of-scope records.

## Frontend conventions
- Inertia pages go in `resources/js/pages/<domain>/`. Domain UI, hooks and schemas go in `resources/js/features/<domain>/`. shadcn primitives live in `components/ui/` (generated). Mawred components live in `components/mw/`.
- Use Wayfinder for routes and generated TS types (`types/generated`) from laravel-data. Never hand-write server shapes.
- **Styling**: tokens only (`design/tokens/mawred-tokens.css` is the theme source of truth, never hand-edited). No raw hex and no arbitrary colour values. Use the status, health and chart tokens for their meaning.
- **UI copy**: the unofficial WhatsApp driver is "Unofficial — WhatsApp Web (WAHA)", never "OpenWA" (D31). The permission matrix shows "Team", which expands to direct reports or tree (D29).
- **RTL**: logical utilities only (`ms/me/ps/pe/start/end/inset-s/border-s/rounded-s`). The linter rejects physical left/right classes. Directional icons go through `<DirIcon>`. Isolate mixed-direction text with `<bdi>`.
- **i18n**: react-i18next, namespace per domain, ICU plurals (Arabic has 6 forms). Format numbers and dates with `Intl` / date-fns respecting `data-digits` and the Hijri preference.
- **Forms**: react-hook-form + zod. Forms built with the form builder validate through `buildZodSchema`. Server 422s map onto fields.
- **Lists**: server-side pagination, filter and sort; TanStack Table v9 (+ Virtual for big grids); `<DataView>` turns into cards below `md`.
- **Async pickers and search**: TanStack Query → `/api/v1` (Sanctum stateful). Always paginated and searchable.
- **Page states**: every page handles empty, loading (skeleton), error, offline and permission-denied via `<PageState>`.
- **Motion**: presets from `lib/motion.ts` (mirroring the CSS tokens). Everything respects `prefers-reduced-motion`. `--ease-spring` is for UI surfaces only, never for data.
- **Responsive**: 360–1920+ px. Sidebar → bottom tabs, dialogs → bottom sheets. Touch targets ≥ 44 px.
- **A11y**: WCAG 2.2 AA. Keyboard-complete, visible focus, labelled controls, live-region announcements for status changes.
- **Assets**: use the SVGs from `design/` as-is (never redraw). Module icons `design/icons/mw-*.svg`; logos `design/logo/<variant>/`; app, PWA, email, WhatsApp and OG assets in `design/app-assets/`. Generic glyphs come from lucide-react.

## Design source paths (actual)
- See `design/README.md`. Canonical folders:
  - Tokens: `design/tokens/mawred-tokens.css` (the brief says `/design/tokens.css`).
  - Logos and app assets: `design/brand/logo/<variant>/`, `design/brand/app-assets/{svg,png}`, `design/brand/manifest.webmanifest`. (`design/logo` and `design/app-assets` are identical older copies.)
  - Module icons: `design/icons/mw-*.svg`.
  - Illustrations: `design/illustrations/current/{svg,png}/{light,dark}/mw-*.svg` (scenes, empty states, system pages, success moments, avatars, certificate frame). `previous-version/` is history only; don't use it.
  - Canvas source: `design/canvas-source/*.dc.html`. The **motion spec is `Motion.dc.html`** (Brand board 07). DS pages: `DSActions`, `DSNavigation`, `DSData`, `DSFeedback`; also `Color`, `Type`.
- **`design/handoff.md`**: the per-screen build spec (44 screens) and the global responsive/RTL/dark/motion rules. Read the screen's row before building it.
- Screen frames are still pending (they arrive per phase). Don't invent layouts beyond the handoff and the DS pages; ask.

## Testing (definition of done)
- Pest feature tests for every Action, Policy and API endpoint, including **scope-leak** datasets (e.g. branch A cannot see branch B).
- Unit tests for calculators, rules and node handlers. Run the rules parity fixtures in both Pest and Vitest.
- Workflow scenario tests per seeded request type: approve, reject, return, escalate, delegate, cancel, parallel.
- Pest browser tests for critical journeys in EN **and** AR, with `assertNoAccessibilityIssues()`.
- Vitest for complex components (form renderer, roster grid, canvas serialisation).
- Visual check via Playwright or Chrome DevTools MCP for every new or changed screen: 390/834/1440 × LTR/RTL in light mode, plus dark for ★ screens (handoff + D32). Compare against `design/screens/` when the frame exists.
- Before you say "done", run: `composer lint && composer analyse && composer test` and `pnpm lint && pnpm typecheck && pnpm test && pnpm build`. Never claim green without running them.

## Commands
- **Local services**: `docker compose up -d` (Postgres 18 on 5442, Redis on 6389, Gotenberg on 3001, Mailpit on 8025). WAHA and ClamAV are opt-in profiles: `docker compose --profile whatsapp --profile clamav up -d`.
- **Fresh install**: `php artisan migrate:fresh --seed`, then `php artisan mawred:install --name=… --email=…`, then open the printed one-time link. Use `--relink` to reissue it. More admins: `php artisan mawred:create-admin --name=… --email=… [--role=hr_admin]` prints a password link (`--relink --email=…` reissues it).
- **Dev**: `composer dev` (server, queue, Reverb on 8090, Vite, logs) or `php artisan serve` with `pnpm build`.
- **Tests**:
  - `composer test`: Pest Unit + Feature + Arch, in parallel, on Postgres.
  - `composer test:browser`: Pest browser tests (Playwright, EN + AR, axe). Run `pnpm build` first.
  - `composer test:performance`: volume and speed checks (10k-row import, 5,000-employee lists < 300 ms). Slow; uses DatabaseTruncation, so never run it alongside other suites.
  - `pnpm test`: Vitest.
- **Quality**:
  - `composer lint` (Rector, then Pint) / `composer lint:check`
  - `composer analyse` (Larastan level max; the baseline may only shrink)
  - `pnpm lint`, `pnpm types:check`, `pnpm format:check`
- **Generated code**:
  - `php artisan typescript:transform` (Data → `resources/js/types/generated.d.ts`; CI fails if stale)
  - `php artisan wayfinder:generate --with-form`
  - `pnpm icons` (module icons from `design/icons`)
- **Seeders**: `Database\Seeders\Production\ProductionSeeder` (roles, permissions, employment types, document types; idempotent). `Demo\DemoSeeder` (3 branches, ~115 people, one sign-in per role sharing `DEMO_PASSWORD` from `.env`). `Performance\PerformanceSeeder` (`PERF_BRANCHES`/`PERF_EMPLOYEES`, default 50/5,000).
- **Production image**: `docker build -f docker/app/Dockerfile -t mawred/app .`. The same image runs app, horizon, scheduler and reverb (`compose.prod.yaml`).

## Gotchas learned in Phase 0
- Docker Desktop can't bind-mount from `/Volumes/Rag`; bake config into images (see `docker/postgres`).
- `tailwind-merge` must know the token type scale (`lib/utils.ts`), or `text-body` silently drops colour classes.
- Logos and illustrations: pass layout classes to the wrapper; never add `hidden`/`block` to the inner `<img>`s.
- `Event::fake()` also fakes Eloquent events: unique ids come from `HasUlids` (insert-time), never `creating` listeners.
- Cached objects need `config/cache.php` `serializable_classes`; only value objects listed there may be cached.
- Wayfinder 0.1 can't type `Route::redirect` (QUERY verb); use a GET closure that returns `to_route()`.
- Rector's Laravel set rewrites code (Carbon → `Date` facade, anonymous classes → attributes); re-read files before editing them.

## Gotchas learned in Phase 1
- Pest's HTTP test client does not URL-encode Arabic query strings: use `rawurlencode()` or the request silently matches nothing.
- `RefreshDatabase` wraps each test in one transaction; thousands of nested `DB::transaction` savepoints exhaust Postgres' lock table. Volume tests live in `tests/Performance` (DatabaseTruncation).
- Server strings go through `__()` and need an entry in `lang/ar.json`; `ServerTranslationsTest` fails otherwise.
- `field_permissions` has a composite key (role_id, field_key): write with `upsert`, never Eloquent `save`, which keys on role_id alone.

## Gotchas learned in Phase 2
- A new UI namespace (`lang/*/ui/<ns>.json`) must be mapped to its page prefix in `resources/js/i18n/index.ts` (`PAGE_NAMESPACES`), or pages render raw keys. Pages that borrow a feature (profile tab, setup step) need it too.
- Policy values change only through `SetPolicyValues` / `AssignPolicySet` (they close the open row and bump `PolicyEpoch`); read them with `PolicyResolver`, never from `policy_values` directly.
- Calculators stay pure: no models, clock or container. Feed them value objects (`HolidayRuleSpec`, value Data), so they run in the Unit suite.
- Number inputs with a unit adornment must not force `dir="ltr"`: the end padding then lands on the wrong side in Arabic.

## Gotchas learned in Phase 3a
- Postgres `jsonb` reorders object keys: compare stored schemas with `toEqual`, never `toBe`.
- Rules live twice (PHP + TS). Change both together and add a case to `tests/fixtures/rules`; both suites run every fixture.
- Inertia's payload type can't hold arbitrary JSON (rule literals are `unknown`): send schemas and answers through `asPayload()` in `features/forms/schema.ts`.
- `Omit<…>` on a type with an index signature drops its named properties; give such contexts an explicit type (`FormRuleContext`).
- ALTCHA at production cost takes seconds to solve; tests set `services.altcha` to a tiny work factor.
- Fresh test schemas drop hundreds of partition tables at once: Postgres needs `max_locks_per_transaction=256` (compose.yaml and CI).
- react-hook-form: read live values with `useWatch`, not `form.watch()` (the React Compiler lint rejects it).

## Gotchas learned in Phase 3b
- Workflow follow-ups (next steps, sub-workflow children, parent wake-ups) dispatch **after** the instance lock is released; with the sync queue anything dispatched inside the lock deadlocks.
- `current()`, `decide()` and other short helper names in Pest files are global: they collide with PHP builtins or other test files. Prefix them.
- `Mail::later()` on the fake drops the delay; set `->delay()` on the mailable and `queue()` it so tests can assert it.
- The runtime `mawred` mailer is built from the vault on first use of the mail manager; tests turn it off with `MAIL_RUNTIME_CONFIG=false`. Type against `MailManager` only after an `instanceof` check (`Mail::fake()` swaps in `MailFake`).
- Text clicks in Pest browser tests match the first element containing the text ("Approve" also matches "Approvals"); target buttons with a `data-*` attribute.
- Domain `Queries\*` must extend `ScopedQuery` (arch test); deliberately unscoped lookups belong in `Support`.

## Gotchas learned in Phase 4
- Inbound webhooks live in `routes/hooks.php`, registered outside the `web` group (no session or CSRF); each controller authenticates the caller itself. Add new hook names to `PUBLIC_ROUTE_NAMES` in the route arch test.
- PHP turns `hub.mode` query keys into `hub_mode`; read both when handling Meta's verification GET.
- Arch rules: no `sha1` or `tempnam` (use `hash('sha256', …)` and `storage_path('app/tmp')`), and every class in `Actions` has exactly one public `handle()`.
- A new encrypted cast must be added to `Domain/Integrations/Support/EncryptedColumns` (a test enforces it) so `mawred:reencrypt` covers it.
- Postgres `jsonb` reorders keys in stored mappings: compare decoded structures, not built URLs or JSON strings.

## Gotchas learned in Phase 5a
- Leave balances come only from `LeaveBalances` (ledger + pending holds + projected accrual); never sum `leave_ledger` yourself. Accrual is posted as a difference against the calculator, so `leave:accrue` is safe to re-run.
- `-1 * 0.0` is `-0.0` in PHP and serialises as `-0`; negate with `0.0 - $x`.
- shadcn `Button` is `shrink-0`: a `w-full` trigger beside another control overflows on phones unless it also gets `min-w-0 shrink`.
- axe rejects `role="row"`/`columnheader` outside a grid or table; a month grid is an `<ol>` of days with an sr-only full date.
- Seeded leave request dates in tests must avoid the Friday/Saturday weekend (Riyadh factory) or the hold refuses them; use `next(MONDAY)`.

## Gotchas learned in Phase 5b-1
- Laravel method injection hands the same instance to two parameters of the same class: a second stateful query (`RosterPeopleQuery`) must be `resolve()`d separately.
- `inTimeZone()` alone doesn't make `Intl` format in that zone; pass `timeZone` to `formatDate` for branch-local times.
- vite-plugin-pwa writes `sw.js` to `public/` but the manifest to `public/build/`; register `/sw.js` yourself (the virtual register module points at `/build/sw.js`).
- TanStack Virtual's `useVirtualizer` trips `react-hooks/incompatible-library`; the line carries a disable comment with the reason.
- A seeded request type whose module ships later is upgraded by `SeedRequestTypes` (new form and workflow versions) when its catalog entry drops `phase`.

## Gotchas learned in Phase 5b-2
- Notification `actions` are generic (`approved`/`rejected`, `accepted`/`tentative`/`declined`); labels come from `ActionLabels`, and WhatsApp shows at most three buttons. Email `.ics` attachments go through `NotificationMessage::$ics`.
- WhatsApp quick replies carry `mawred:<token>`: approval tokens and meeting `ReplyTokens` share the prefix; `HandleWhatsAppReply` tells them apart by shape.
- RFC 5545 COUNT includes skipped months (monthly on the 31st), so `Recurrence` counts them too.
- `composer test:browser` disables Composer's process timeout; the suite takes longer than 300 s.
- The React Compiler purity rule rejects `Date.now()` during render; compute defaults in a lazy `useState` initialiser.

## Gotchas learned in Phase 6a
- Rector's Laravel set renames `$model->events` to `dispatchesEvents`: never name a relation `events` (hiring uses `history`).
- `FormRenderer` sends file answers as upload tokens (`features/forms/submit.ts`); the server rejects file objects. Public uploads only match the session that made them: browser and HTTP tests keep one session cookie.
- Radix `Tabs` defaults to `dir="ltr"`: pass `dir` from `useLocale()` or RTL layouts and dates inside the tabs break.
- Inertia's shared props are built before route middleware runs: anything that changes the locale (careers `?lang`) belongs in `SetLocale`.
- Workflow steps dispatch after commit, so a seeder that wraps actions in one transaction never sees approvals happen; demo seeders also force the sync queue.
- Tests run with `INERTIA_SSR_ENABLED=false` (phpunit.xml): once `bootstrap/ssr` exists, Inertia dispatches SSR over the HTTP client, and `Http::fake(['*' => …])` would answer it with a blank page. Build SSR with `pnpm build:ssr`; production runs it as the `ssr` service.
- Strict models throw on `getAttribute()` for a `withCount` that wasn't loaded; read counts through `Texts::count()`. Eloquent `value()` returns the cast enum, not the raw string.

## Gotchas learned in Phase 6b
- shadcn primitives set `data-slot` on every element: name test hooks something else (`data-booking-slot`), or selectors match the whole page.
- The Arch suite needs more than 512 MB now; phpunit.xml sets `memory_limit=1G` (as PHPStan does).
- Hiring code that needs "today" (offer letters, offer expiry) asks `LocalClock` for the branch's date; the time arch test rejects `now()->toDateString()` in domain code.
- Integrations must not import Hiring for behaviour: Cognito postings go through `Integrations\Contracts\CognitoApplications`, bound in `HiringServiceProvider`.
- Pest browser `drag()` presses the mouse on the source's centre and releases on the target: it draws a stroke across a canvas signature pad.
- Prettier rewrites `lang/*.json` with 2-space indents; the repo keeps 4. Don't run it on lang files (write them with `json.dumps(…, indent=4)`).

## Gotchas learned in Phase 7a
- The React Compiler reads an event handler's dependencies during render: `course!.id` inside an `onClick` closure crashed the page when `course` was null. Pull the id into a local (`course?.id ?? null`) and check it inside the handler.
- Inertia keeps the page component across visits to the same component (create → edit): key the inner editor by the record id, or its `useState` defaults stay from the first visit.
- `case Self` is a fatal error in a PHP enum (reserved word); use `SelfService`.
- A `<legend>` sits on its fieldset's border: put the card border on a wrapper, not on the fieldset.
- `SyncEmployeePlacement` fires `EmployeePlacementChanged` when someone's branch, department, position, employment type or status changes; mandatory courses listen to it.

## Gotchas learned in Phase 7b
- A fixed-width page with `dir="rtl"` on `<html>` prints or screenshots blank in Chromium (Gotenberg): keep the root LTR and put `dir` on the content wrapper.
- `CarbonImmutable::locale($x)` reads as `static|string` to PHPStan; use `->settings(['locale' => $x])->isoFormat(…)`.
- Don't force `dir="ltr"` on times that may contain Arabic AM/PM marks; isolate each time with its own `<bdi>`.
- `<dl>` may only contain `<dt>`/`<dd>` groups wrapped in single `<div>`s; nesting a grid `<div>` inside fails axe.
- The standard request chain's HR step needs an HR user to fall back to in tests (`userWithRoles('hr_admin')`), or the workflow stops with "Nobody can approve this step".

## Gotchas learned in Phase 8a
- Tailwind v4 emits a `--color-*` theme variable only when a class uses it; inline styles (Recharts fills, strokes) must use the raw tokens (`var(--chart-1)`, `var(--border)`), or they fall back to black.
- Recharts 3 makes the chart surface focusable (`accessibilityLayer`): never wrap it in `aria-hidden`, or axe fails `aria-hidden-focus`.
- `ScopeResolver` and `ReportRegistry` are request-scoped; a test that changes someone's roles mid-test calls `app()->forgetScopedInstances()` before running a job, as a queue worker would.
- Report rows go through `BaseReport::scoped()` with the module permission; never filter by branch yourself. Days a run covers come from the `today` that `ParamResolver` stores, never the server clock.
- Rector turns locally-called `private static` helpers into instance methods; write them as instance methods to begin with.

## Gotchas learned in Phase 8b
- PHPStan only accepts raw SQL it can prove is a code literal: dataset field expressions are typed `literal-string` all the way down; column aliases come from a constant list, not string building.
- A JSON request to a web route that throws `DomainRuleViolation` now gets a 422 `{message, errors}` (it used to fall through to a 500); `postJson` reads it.
- A laravel-data `array` property is required even with a default: an empty array posted as a form is dropped. Make it `?array … = null`.
- In Pest, an arrow fn captures by value: a deferred-props callback that writes to an outer `&$rows` must sit inside a `function () use (&$rows)`, not `fn`.
- Typing into a field that enables a button and running axe right away catches the button mid-transition (disabled colours); wait briefly first.

## Gotchas learned in Phase 8c
- An absolutely positioned `sr-only` element escapes an `overflow-x-auto` scroller unless something between them is positioned: give scroller cards `relative`, or the page grows sideways on phones (mobile Chrome then widens the layout viewport, so even the fixed tab bar looks too wide).
- A `<table>` ignores `sr-only`'s 1 px width: wrap a screen-reader table in an `sr-only` div.
- Recharts legends colour their text with the series colour and sort by name: pass `itemSorter={null}` and a `formatter` that renders `text-foreground`.
- SVG `pathLength` draw-ins break with `vector-effect: non-scaling-stroke`; reveal the line with an animated clip rect instead.

## Gotchas learned in Phase 9a
- The CSP is enforced: new inline `<script>`s need `nonce="{{ Vite::cspNonce() }}"`, new outside origins go in `config/csp.php`, and `tests/Browser/ContentSecurityPolicyTest.php` fails on any violation report.
- Every path that keeps an uploaded file calls `UploadGuard::assertClean()` (or goes through `StoreFormUpload`).
- The service worker is `resources/js/sw.ts` (injectManifest): reference the manifest literally as `self.__WB_MANIFEST` or it isn't injected; it only exists in production builds.
- Email to an address on `email_suppressions` is skipped; check `EmailSuppressions::blocks()` in any new email path.
- `NotificationPreferences` caches per request: tests that change a switch mid-test reset it (`forgetInstance`).

## Gotchas learned in Phase 9b
- Browsers read Reverb's address from `<meta name="mawred-reverb">` (`config('mawred.realtime')`), not `VITE_REVERB_*`: the image is built once from `.env.example`, so build-time values would point production at localhost.
- The production image's entrypoint runs `php artisan optimize` before every command; one-off containers that don't need it set `MAWRED_OPTIMIZE=false`.
- Deploy scripts source `deploy/lib.sh` (`set -Eeuo pipefail`): a function called in an `if`/`||` runs without errexit, so `rollout` checks each step itself. People at a shell use `deploy/dc.sh`, never `source lib.sh`.
- macOS has no `flock`: to try the scripts locally, run them in a `docker:cli` container with util-linux and the Docker socket mounted, under a separate `COMPOSE_PROJECT_NAME`.
- Secrets never go on a `docker run` command line (`ps` shows them): export them and pass `-e NAME` alone, or read them from the container's own env file.
- Production's edge is the host's nginx (D134): containers publish on 127.0.0.1 only, and the app is `app-blue` or `app-green` (`.deploy/live-slot`). `deploy/dc.sh … app …` means the live slot; scripts use `app_service`.
- nginx's default proxy header buffer (4–8 KB) is smaller than the app's response headers (CSP nonce, preload links, cookies) and turns pages into 502s; `deploy/nginx/mawred-proxy.conf` sets 32 KB.
- The SSR server runs `app.tsx` on Node: guard browser globals at module level (`typeof document`). The image has no `node_modules`, so `vite.config.ts` bundles every dependency into SSR (`ssr.noExternal`).
- Vite's dev host is pinned to `localhost`: browsers ignore `[::1]` in a CSP, which blanked pages under `composer dev`.

## Git
- Branches `feat/<phase>-<slug>`, `fix/<slug>`. Conventional commits. Open a PR per feature, and CI must be green before merge.
- Commit or push only when asked. Never commit secrets: integration credentials live in the DB vault and infra secrets in env.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
