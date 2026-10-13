# Mawred (مَورِد) — project conventions for Claude

An internal HR, hiring and workflow platform for one legal entity with unlimited branches. Laravel 13 modular monolith + Inertia v3 / React 19 / TS / Tailwind 4 / shadcn. Bilingual AR/EN, RTL-first.

Laravel Boost guidelines (generated, git-ignored; run `php artisan boost:install` after cloning): @AGENTS.md

**Read first**: [docs/architecture.md](docs/architecture.md) · [docs/adr/](docs/adr/README.md) · [docs/roadmap.md](docs/roadmap.md) · [docs/stack-versions.md](docs/stack-versions.md) · [docs/open-questions.md](docs/open-questions.md) (decision log D1–D27)

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
- **Fresh install**: `php artisan migrate:fresh --seed`, then `php artisan mawred:install --name=… --email=…`, then open the printed one-time link. Use `--relink` to reissue it.
- **Dev**: `composer dev` (server, queue, Reverb on 8090, Vite, logs) or `php artisan serve` with `pnpm build`.
- **Tests**:
  - `composer test`: Pest Unit + Feature + Arch, in parallel, on Postgres.
  - `composer test:browser`: Pest browser tests (Playwright, EN + AR, axe). Run `pnpm build` first.
  - `pnpm test`: Vitest.
- **Quality**:
  - `composer lint` (Rector, then Pint) / `composer lint:check`
  - `composer analyse` (Larastan level max; the baseline may only shrink)
  - `pnpm lint`, `pnpm types:check`, `pnpm format:check`
- **Generated code**:
  - `php artisan typescript:transform` (Data → `resources/js/types/generated.d.ts`; CI fails if stale)
  - `php artisan wayfinder:generate --with-form`
  - `pnpm icons` (module icons from `design/icons`)
- **Seeders**: `Database\Seeders\Production\ProductionSeeder` (roles and permissions; idempotent). Demo and Performance seeders arrive in Phase 1.
- **Production image**: `docker build -f docker/app/Dockerfile -t mawred/app .`. The same image runs app, horizon, scheduler and reverb (`compose.prod.yaml`).

## Gotchas learned in Phase 0
- Docker Desktop can't bind-mount from `/Volumes/Rag`; bake config into images (see `docker/postgres`).
- `tailwind-merge` must know the token type scale (`lib/utils.ts`), or `text-body` silently drops colour classes.
- Logos and illustrations: pass layout classes to the wrapper; never add `hidden`/`block` to the inner `<img>`s.
- `Event::fake()` also fakes Eloquent events: unique ids come from `HasUlids` (insert-time), never `creating` listeners.
- Cached objects need `config/cache.php` `serializable_classes`; only value objects listed there may be cached.
- Wayfinder 0.1 can't type `Route::redirect` (QUERY verb); use a GET closure that returns `to_route()`.
- Rector's Laravel set rewrites code (Carbon → `Date` facade, anonymous classes → attributes); re-read files before editing them.

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
