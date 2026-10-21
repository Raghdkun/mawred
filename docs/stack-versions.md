# Verified stack versions (snapshot 2026-10-13)

Checked against Packagist, npm and GitHub. Re-verify with Laravel Boost and Context7 when installing in Phase 0. ⚠ marks a deviation from the brief or a risk.

## PHP
| Package | Version | Notes |
|---|---|---|
| laravel/framework | 13.x (13.36) | PHP 8.3–8.5. Upgrade local PHP 8.4.1 to the latest 8.4.x |
| altcha-org/altcha | 2.3 | Public form captcha (D10, D53): proof-of-work challenge and verification, HMAC key from the vault |
| minishlink/web-push | 11.0 | Web push (D128): VAPID signing and payload encryption for the `webpush` notification channel; PHP 8.2+, MIT. Needs ext-gmp in the image for speed (added to `docker/app/Dockerfile`) |
| Starter kit (react) | main | Inertia **v3**, React 19.2 + Compiler, Vite **8**, Tailwind 4.3, Fortify (2FA + passkeys), Wayfinder 0.1, SSR on |
| inertiajs/inertia-laravel | 3.x | `Inertia::lazy` removed → `optional()` |
| laravel/sanctum / horizon / reverb / pennant / pulse / boost | 4.3 / 5.50 / 1.12 / 1.26 / 1.8 / 2.10 | all support L13. ⚠ Reverb pins guzzlehttp/psr7 ^2.6, so Guzzle stays on 7.x |
| spatie/laravel-permission | 8.x | v7/v8 renamed events and commands; contracts accept `BackedEnum` |
| spatie/laravel-activitylog | 5.x | ⚠ PHP 8.4; `batch_uuid` removed; namespaces moved |
| spatie/laravel-medialibrary / translatable / data / query-builder / backup | 11 / 6 / 4 / 7 / 10 | |
| spatie/laravel-typescript-transformer | 3.x (stable) | v3 is a rewrite. ⚠ laravel-data's own transformer targets v2, so Data classes use `#[TypeScript]` + `AttributedClassTransformer` |
| dedoc/scramble | 0.13 | 0.x |
| openspout/openspout | 5.x | PHP 8.4 |
| sentry/sentry-laravel | 4.29 | |
| propaganistas/laravel-phone | 6.x | E.164 normalisation (libphonenumber lite); L13 + PHP 8.4. Branch `phone_region` is the default region |
| maplibre-gl | 6.14 | Geofence maps (D33) with OpenFreeMap tiles. Loaded on demand (own ~1 MB chunk); its ES-module worker is bundled with `?worker&url` and set via `setWorkerUrl`. Arabic shaping built in |
| laravel-lang/common (dev) | 6.x | Publishes framework Arabic (`lang/ar/*.php`, `lang/ar.json`); app strings are added to `lang/ar.json` and guarded by a test |
| pestphp/pest | ⚠ **5.x is current** (4.7 is the last v4) | **Decided: Pest 5** + PHPUnit 13 (D16) |
| larastan/larastan | 3.x (PHPStan 2, level 10 max) | |
| rector/rector + driftingly/rector-laravel | 2.7 / 2.6 (`LARAVEL_130` set) | |
| gotenberg/gotenberg-php | 2.25 (server 8.37) | |
| laravel-notification-channels/webpush | 13.x | L13 |

## JS
| Package | Version | Notes |
|---|---|---|
| @inertiajs/react | 3.x | Axios removed (built-in client) |
| @xyflow/react | 12.12 | |
| @dnd-kit/core + sortable | 6.3 | ⚠ classic API, no release since 2024-12; `@dnd-kit/react` 0.5 is pre-1.0 |
| @tanstack/react-table / react-virtual / react-query | 9.x / 3.14 / 5.x | shadcn data-table uses v9 |
| motion | 14.x | |
| recharts | 3.x | shadcn chart pins 3.8 |
| sonner / cmdk | 2.0 / 1.1 | |
| date-fns (+ @date-fns/tz) | 4.x | |
| react-hook-form / zod / @hookform/resolvers | 7.90 / 4.x / 5.x | |
| i18next / react-i18next | 26 / 17 | `laravel-react-i18n` unmaintained since 2024-10 |
| vite-plugin-pwa (+ workbox-build / workbox-window) | 2.0 (7.4) | Vite 8 ok (peer `^8.0.0`). Installable PWA and the offline punch queue (D70) |
| qr-scanner | 1.4.2 | MIT; kiosk QR scanning, uses the native BarcodeDetector when present (D70). No React peer |
| vitest / @testing-library/react | 5.x / 16.3 | |
| playwright | 1.64 | for pest-plugin-browser 5.1 (`pnpm exec playwright install chromium`) |
| radix-ui (unified) | 1.8 | new shadcn components import from `radix-ui`; ⚠ the CLI wrote `import { cn } from "cn"`, so check generated files |
| Lint | **ESLint 9.39 + Prettier**, replacing the kit's vite-plus (D17). ⚠ Not ESLint 10: `eslint-plugin-react` and `eslint-plugin-jsx-a11y` don't support it yet | `eslint-plugin-better-tailwindcss` (logical-properties rule) works with both |
| @fontsource-variable/readex-pro | 5.3 | OFL-1.1, arabic + latin subsets, wght 160–700 |
| @dnd-kit/core / sortable / utilities | 6.3 / 10.0 / 3.2 | Form builder reordering (D52). Peer `react >=16.8`, works with React 19 |
| altcha (web component) | 3.3 | Public form captcha widget (D10, D53) |
| @xyflow/react | 12.12 | Workflow builder canvas (D52, handoff 15). Peer `react >=17`, works with React 19; 13.0 is still `next` |

## Infra
PostgreSQL 18, Redis 8, Gotenberg 8.37, FrankenPHP 1.13, Caddy 2, WAHA (GOWS engine, pinned tag), ClamAV, GlitchTip. WhatsApp Graph API v25/v26. Captcha: altcha-org/altcha (PHP) + altcha web component.

## Dependency audit (Phase 9a, 2026-10-20)
- `composer audit`: no advisories. Abandoned but harmless transitive packages: `dragon-code/pretty-array` and `dragon-code/support` (via laravel-lang) and `symplify/rule-doc-generator-contracts` (via rector-laravel, dev only). Revisit when laravel-lang drops them.
- `pnpm audit` (all dependencies): no known vulnerabilities.
