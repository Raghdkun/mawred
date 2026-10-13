# Decision log (grill-me, 2026-10-13)

All questions below were answered by the product owner. Items still waiting on an external input are marked ⏳.

| # | Topic | Decision | Affects |
|---|---|---|---|
| D1 | Design handoff | ✅ `design/handoff.md` received (44 screens, global rules, component inventory). ⏳ Canvas exports still pending (D28). | All UI |
| D2 | Countries | **Mixed MENA + other.** Full multi-currency (`Money` = amount_minor + ISO 4217 currency), default currency and default phone region per branch, multi-timezone from day one. No FX in v1 (no report sums money). | ADR-0022, Organization, Hiring offers |
| D3 | Locales | **EN + AR, default EN.** Per-user choice; architecture open to more. | ADR-0011 |
| D4 | First admin | **`php artisan mawred:install`** creates the owner and prints a one-time setup link (24 h). Wizard step 6 sets the password, forces 2FA and invites admins. | Setup |
| D5 | Role scope | **Anchors per assignment** (branches/departments). Without anchors, the user's own placement is used. | ADR-0005 |
| D6 | Users vs employees | **Optional link**: one user ↔ at most one employee. Non-employee users (external auditor, interviewer, bot) get anchored or `assigned` scopes only. | Access, People |
| D7 | Payroll | **No payroll; monthly payroll-input export** per branch (overtime by multiplier, unpaid leave, lateness minutes, allowance changes). | Reports (data P5, export P8) |
| D8 | Unofficial WhatsApp | **WAHA** (Apache-2.0, GOWS/NOWEB engine), labelled "Unofficial (WhatsApp Web)", with the ban-risk warning. | ADR-0015 |
| D9 | Email provider | **Undecided → provider-agnostic SMTP.** Bounce webhooks are added once a provider is chosen. | ADR-0015 |
| D10 | Captcha | **ALTCHA (self-hosted proof-of-work)** plus honeypot, minimum fill time and rate limits. HMAC key in the vault. | ADR-0008 |
| D11 | Hostnames | **`hr.<domain>`** (app, /api/v1, websockets) + **`careers.<domain>`** (public, SSR). Actual domain ⏳. | ADR-0017/0019 |
| D12 | Holidays | **US Federal holiday preset** (nth-weekday rules + observed-date shifting). **No Hijri-recurring rules**; Islamic holidays are one-off entries or CSV. The optional Hijri *display* stays. | ADR-0010, Policies |
| D13 | Precedence | **Employment type beats grade**: global < branch < department < position < grade < employment type < employee. | ADR-0010 |
| D14 | Repo | **`Raghdkun/mawred`**, private. Remote is created in Phase 0 after the plan is approved. | CI |
| D15 | Backups | **Hostinger Object Storage.** Mitigations: bucket-scoped write-only keys, encrypted backup archives, versioning/object-lock if available, APP_KEY escrowed separately. | ADR-0019 |
| D16 | Test framework | **Pest 5** (PHPUnit 13). | Tooling |
| D17 | JS lint | **ESLint 10 + Prettier**; remove the kit's vite-plus. Plugins: better-tailwindcss (logical, inline axis only), jsx-a11y, react-hooks, i18next/no-literal-string. | ADR-0011/0020 |
| D18 | Data protection | **No specific regime.** Everything is a setting, with neutral starting values flagged "review with legal" on the setup checklist. | Hiring, Audit, Retention |
| D19 | Cognito | **Greenfield.** Synthetic fixtures now; a real sample is captured in the Phase 4 wizard test. | Phase 4 |
| D20 | ClamAV | **On, scan all uploads; KVM 4 (4 vCPU / 16 GB).** | ADR-0019 |
| D21 | Error monitoring | **GlitchTip self-hosted** on the VPS (Sentry SDK, shared Postgres/Redis, separate DB). Plus a free external uptime ping, because GlitchTip can't report its own host going down. | ADR-0019 |
| D22 | Company accent | **Co-brand areas only** (login, email header strip, PDF letterhead, careers site). The app UI stays Mawred tokens. | Setup step 2 |
| D23 | Local dev | **Hybrid**: native PHP (upgrade to the latest 8.4.x) + Node; Docker for PG 18, Redis, Gotenberg, Mailpit, WAHA, ClamAV, GlitchTip (optional). | Phase 0 |
| D24 | Link approvals | **Token + confirmation; login (with 2FA if required) for request types flagged sensitive.** | ADR-0009 |
| D25 | Concurrent jobs | **One primary + optional secondary assignments.** The primary drives policy, manager chain, scope and payroll export; secondaries add rostering and visibility. | People, Scheduling, ADR-0005 |
| D26 | Geofence | **Policy-driven per branch: `block` / `flag` (default) / `off`.** Flagged punches need a reason and go to the daily board. | Attendance, Policies |
| D27 | API coverage | **Mobile-first slice per phase**: employee- and approver-facing features plus whatever the web async widgets need. Admin builders stay web-only. | ADR-0012, roadmap |

| D28 | Canvas assets | **Exported into `/design`**: `design/motion/` (Brand board 07 motion spec), `design/illustrations/` (SVG), `design/screens/` (PNG/SVG frames, arriving per phase). ⏳ | Motion, PageState, visual QA |
| D29 | Team scope | **Keep both**: the matrix cell shows "Team", which expands to *Direct reports* or *Whole team tree* (default: tree). "None" means the permission is absent. | ADR-0005, screen 38 |
| D30 | Precedence display | **6 visual levels, 7 logical**: the "Employment type / grade" card shows both sub-levels (grade, then employment type, which wins). The explainer chip names the exact sub-level. | ADR-0010, screens 18–19 |
| D31 | Unofficial WA label | **"Unofficial — WhatsApp Web (WAHA)"** everywhere the handoff says "OpenWA". The design copy is updated to match. | ADR-0015, screens 40–41 |
| D32 | Dark mode | **Dark everywhere; launch visual QA covers the 9 ★ screens.** Other screens get automated axe/contrast checks in dark mode and a full dark visual pass in Phase 9. | Visual QA matrix |
| D33 | Maps | **MapLibre GL + OpenFreeMap tiles** (no key). Nominatim (debounced, attributed) for address search, plus manual lat/lng entry. Map controls stay LTR. | Setup step 4, screen 39 |

## Still open (non-blocking)
- Production domain name (needed by Phase 6 careers and Phase 9 deploy).
- Transactional email provider (needed before go-live for bounce handling).
- Team size and target dates (to turn relative phase sizes into a calendar).
