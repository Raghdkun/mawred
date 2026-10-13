# Mawred — Screen Handoff Summary

Oct 13, 2026 · @George Warren

## How to use this doc

This is the build spec for all 44 Mawred screens. Each screen is listed with the components it uses and how it changes across breakpoints and in Arabic. Screen designs on the canvas will follow in the brief's order; this doc is updated as each one lands.

- **Source of truth for visuals:** the Mawred canvas (Brand board, Design system and Illustrations pages).
- **Source of truth for values:** `mawred-tokens.css` (Tailwind v4 `@theme` + shadcn/ui variable names, OKLCH).
- **Component names** below match the Design system page: Actions & inputs, Navigation, Data display, Overlays & feedback.
- **Breakpoints:** desktop 1440, tablet 834, mobile 390. Layout switches at 1280 (sidebar collapses to 72 px) and 768 (bottom tab bar replaces the sidebar).
- **Every screen ships** in EN-LTR and AR-RTL, light mode. Screens marked ★ also ship in dark mode.
- **Scale:** every list, picker and switcher assumes hundreds of branches and thousands of employees: search-first, server-paged, virtualized.

## Global rules

These rules apply to every screen and are not repeated in the tables below.

**Responsive**

| Breakpoint | Navigation | Content | Overlays |
| --- | --- | --- | --- |
| 1440 desktop | Sidebar 260 px + top bar 64 px | 12-col grid, 24 gutter, max content 1280 | Dialogs centered; detail opens in a 440 px drawer from the reading end |
| 834 tablet | Sidebar collapsed to 72 px with tooltips | 8-col grid; split views become list → detail | Drawers go full height, 560 px |
| 390 mobile | Bottom tab bar (Home, Requests, New, Schedule, Me) + top app bar | Single column; tables become cards | Bottom sheets (snap 50% / 92%) replace drawers and popovers |

**RTL (Arabic)**

- Set `dir="rtl"` and `lang="ar"` on `<html>`; build with logical properties only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `inset-inline-*`).
- Mirror: arrows, chevrons, breadcrumbs, progress bars, sliders, steppers, drawers, swipe directions, drag tilt, page-enter slide, the Requests/Workflows/Interviews icons and the Returned status glyph.
- Never mirror: checkmarks, clocks, media controls, search, charts, logos, map pins, phone numbers, IDs, IBANs, URLs and code (wrap these in `dir="ltr"`).
- Arabic text uses its own line heights (body 1.75, headings 1.42) and no letter-spacing or uppercase.
- Digits: Western by default; Arabic-Indic is a per-user preference. Never mix systems in one view. Hijri dates show as a secondary line under Gregorian when enabled.

**Dark mode** covers ★ screens at launch: Login, Employee home, HR admin home, Approvals inbox, Form builder, Workflow builder, Roster planner, Attendance check-in, Pipeline kanban. Base #0E1626, surfaces #142036 / #1B2A45; primary flips to Mist 400 with navy text.

**Motion:** use the motion spec on the Brand board (07). Every animation has the listed reduced-motion fallback under `prefers-reduced-motion: reduce`.

## Auth, setup and shell (1–3)

The setup wizard is the first thing a Super Admin sees; it is resumable and every optional step can be skipped and revisited from Review.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 1 | Login ★ | Input, password input with reveal, checkbox (remember me), primary button, language toggle, toast | Desktop/tablet: split 50/50, hero illustration on the reading-end half. Mobile: illustration shrinks to a 160 px header band | Hero sits on the left in AR. Error: inline field error + shake-free danger text. Loading: button spinner |
| 1a | 2FA | 6-cell OTP input, resend link with countdown, primary button | Same card centered at all widths | OTP cells stay LTR. Auto-advance; paste fills all cells |
| 1b | Forgot password | Input, primary button, success empty state | Centered card | Success shows the “check your email” state with ripple |
| 1c | First-login onboarding | 3-slide carousel, dots, skip, primary button | Desktop: 720 px modal. Mobile: full screen with swipe | Swipe and slide order mirror; dots fill from the reading start |
| 2 | Setup wizard (10 steps) | Side progress rail, compact stepper (mobile), inputs, file dropzone (logo), color preview, select, map with geofence + slider, preset cards, toggle, copy field, masked secret, test-connection button, review list, success ripple | Desktop: rail 300 px + form 640 px + live preview. Tablet: rail collapses to the compact bar. Mobile: compact bar; full list in a bottom sheet | Rail fills like water. Skipped steps keep a dashed bullet. Map controls stay LTR; radius slider mirrors. Finale = “You’re all set” scene |
| 3 | App shell | Sidebar with branch switcher, top bar (⌘K search, language toggle, notifications, profile), bottom tab bar, FAB “New request”, command palette | See Global rules | Sidebar on the right in AR. Company logo slot + “powered by Mawred” in the sidebar footer |

## Dashboards (4–6)

Home is role-based: one route, three layouts chosen by the user's highest role, with a switcher for people who hold several.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 4 | Employee home ★ | Greeting, clock-in card (geofence chip), leave balance cards with progress ring, my pending requests (status badges), agenda list (shifts, meetings, trainings), quick-request buttons | Desktop: 2 columns (8/4). Tablet: 1 column, balances in a 3-up row. Mobile: clock-in card first, quick requests as a horizontal scroller | Scroller starts at the reading start. Empty agenda uses the schedule empty state |
| 5 | Manager home | Approvals queue (approval cards), team availability strip (avatar stack + counts), lateness alerts (notification items), KPI cards | Desktop: queue 8 cols + availability/alerts 4 cols. Mobile: approval cards with swipe-to-approve | Swipe toward the reading end approves, toward the start rejects. Undo toast after each swipe |
| 6 | HR admin home ★ | KPI cards with sparkline (headcount, turnover, open positions, pending approvals), expiring documents table, attendance today (stacked bar), integration health widget, alert banner | Desktop: 4 KPIs in a row. Tablet: 2×2. Mobile: KPIs as a horizontal scroller, tables as cards | Banner appears above the grid when any integration is Disconnected. Numbers count up; charts draw in |

## People (7–9)

The directory must stay fast at thousands of records: server-side search, filters and paging, with virtualized rows and cards.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 7 | Employee directory | Pill tabs (Table / Grid / Org chart), filter bar with saved views, data table (bulk actions, column picker, density), employee cards, org chart nodes with expand, async picker for manager jump | Desktop: table. Tablet: table with fewer columns. Mobile: cards; org chart becomes an indented tree | Org chart grows toward the reading end. Empty: employees empty state. No results: search empty state |
| 8 | Employee profile | Header (avatar, name, role, status badge), tabs (Overview, Job, Personal, Documents, Leave, Attendance, Training, History, Effective policies), document rows with expiry badges, timeline, policy source explainer chips | Desktop: header + tabs + 2-col content. Mobile: tabs become a horizontal scroller; sections stack | Tab order mirrors. Expiry badge: warning ≤ 30 days, danger when expired |
| 9 | Bulk import wizard | Stepper (Upload → Map columns → Validate → Confirm), file dropzone, column-mapping table with selects, validation table with row errors, progress bar, success state | Desktop: full-width table. Mobile: import is desktop-only; mobile shows a “continue on desktop” card | Error rows link to the cell. CSV/Excel column headers keep their own direction |

## Requests, forms and workflows (10–15)

Native and Cognito-backed request types render identically to employees; the source only shows to admins.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 10 | Request center | Search, category pills, request-type cards (icon, name, one-line description, “needs approval” hint) | Desktop 4-up, tablet 3-up, mobile 2-up cards | Cards flow from the reading start. Admin-only source tag (Native / Cognito) |
| 11 | Request form, review, submitted | Form fields from the builder, date range picker with Hijri, balance preview, policy chip, review summary, primary button, success ripple scene | Desktop: form 640 px + summary aside. Mobile: single column, sticky submit bar | Submitted = “request submitted” illustration with rings from the check |
| 12 | My requests + detail | Filter bar, list rows with status badges, detail with live workflow timeline, comment thread, withdraw (destructive, confirm dialog) | Desktop: list + drawer. Mobile: list → full-screen detail | Timeline rail on the reading-start side. Empty: requests empty state |
| 13 | Approvals inbox ★ | Split view (list + approval card detail), bulk select + approve, delegate dialog with async picker, SLA badge | Desktop: 400 px list + detail. Tablet: list → detail. Mobile: approval cards with swipe | Swipe directions mirror. Bulk approve shows a count-up toast. Empty: inbox empty state |
| 14 | Form builder ★ | Field palette, canvas with drag-to-reorder, properties panel (bilingual labels, validation, conditional logic), EN/AR preview toggle, phone preview | Desktop-only (1280+). Tablet shows read-only preview | Palette on the reading-start side, properties at the end. Each label has EN + AR inputs side by side |
| 15 | Workflow builder ★ | Node canvas (start, form, approval, condition, parallel/join, notify email/WhatsApp/in-app, delay/SLA, webhook, update record, end), node config side panel, version history, test-run simulator | Desktop-only (1280+) | Flow runs in the reading direction; canvas mirrors in AR. Simulator highlights the active path with a ripple |

## Policies (16–21)

Every policy value resolves through one precedence chain; the explainer chip shows which level supplied it.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 16 | Policy sets list | Data table (name, preset badge, branches using it with avatar-stack style count, effective dates), filter bar, empty state | Desktop table; mobile cards | Empty: policies empty state |
| 17 | Policy editor | Section nav (leave types, accrual, carry-over/expiry, weekends, working hours, overtime multipliers, lateness/grace, rest hours, probation/notice), number inputs with units, segmented controls, weekday chips, multiplier table, sticky save bar | Desktop: section nav + form. Mobile: read-only summary with “edit on desktop” | Weekday chips start from the branch's first day. Units sit at the reading end of inputs |
| 18 | Assignment & precedence | Precedence chain diagram, level cards with assigned sets, async pickers for scope | Desktop: horizontal chain. Mobile: vertical chain | Chain runs in the reading direction |
| 19 | Effective policy for employee | Employee picker, value table with policy source explainer chip per row, popover with full chain | Desktop table; mobile cards | Overridden values carry the orange dot |
| 20 | Change-impact preview | Summary banner (“affects 37 employees”), balance delta table (before, after, delta), confirm dialog | Desktop table; mobile cards | Deltas use tabular figures; negative deltas in danger text with a minus sign, never color alone |
| 21 | Holiday calendar manager | Branch picker, month/year calendar with holiday cells, CSV import dropzone, recurring-rule form, list view | Desktop: calendar + list. Mobile: list only | Hijri-based holidays (e.g. Eid) show both calendars |

[embedded content: policy precedence · 6 levels, general to specific]

Screens 18 and 19 draw this chain in the reading direction; in Arabic it runs right to left.

## Schedule, time and calendar (22–27)

The roster planner is the heaviest screen: it must virtualize both rows (employees) and columns (days).

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 22 | Roster planner ★ | Segmented control (Week / 2 weeks / Month), employee × day grid, shift blocks (drag, conflict, open shift), open-shifts row, coverage heatmap row, publish bar with unpublished count | Desktop-first; tablet scrolls horizontally with a sticky name column; mobile shows My schedule instead | Day columns run in the reading direction; name column sticks to the reading start. Drag lift tilts −1.5° in AR |
| 23 | My schedule | Agenda list grouped by day, month calendar toggle, shift blocks, swap request button | Mobile-first; desktop shows calendar + agenda side by side | Empty: schedule empty state |
| 24 | Leave calendar | Filter bar (team, branch, department), month grid with leave events, holiday cells, legend | Desktop month grid; mobile agenda | “+3 more” opens a popover (bottom sheet on mobile) |
| 25 | Attendance check-in ★ | Big clock-in button with ripple, geofence status chip (inside / outside / checking), QR scan mode, offline-queued banner, last punches list | Mobile-first; desktop shows the same card centered | Offline: punch queues with time stamp and syncs later. Outside geofence: button disabled with reason |
| 26 | Attendance admin | Daily board columns (present / late / absent / on leave) with counts, data table, correction drawer with reason | Desktop board; mobile tabs per status | Corrections write to the audit log |
| 27 | Meetings & interview scheduler | Participant async picker, availability finder grid (free/busy), slot chips, booking dialog, “add to my calendar (.ics)” button | Desktop grid; mobile list of suggested slots | Time axis stays LTR in digits but the grid mirrors. No external calendar sync |

## Hiring (28–32)

The public careers pages carry the customer's brand; Mawred appears only as “powered by” in the footer.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 28 | Job requisitions | Data table with approval status badges, filter bar, requisition drawer with workflow timeline | Desktop table; mobile cards | Empty: candidates empty state variant |
| 29 | Pipeline kanban ★ | Configurable stage columns with counts, kanban cards (score, tags, days in stage, interviewers), drag between stages, stage settings | Desktop: horizontal columns. Tablet: horizontal scroll. Mobile: one stage at a time with a stage switcher | Columns run in the reading direction. Drag tilt mirrors |
| 30 | Candidate profile | CV viewer (PDF), scorecards, interview feedback list, communications log (email + WhatsApp), offer section, timeline | Desktop: CV left/right pane + details. Mobile: tabs | CV pane sits at the reading start. Arabic CVs render RTL inside the viewer |
| 31 | Careers page, job detail, apply | Branded header (company logo), job cards, filters, job detail, application form (dropzone for CV), success state | Mobile-first; desktop 2-column job detail | Bilingual with a language switch; OG image per language |
| 32 | Offer letter preview | Document preview, variable chips, signature pad, send dialog (email/WhatsApp) | Desktop preview + side panel; mobile full-screen preview | Letter direction follows the candidate's chosen language |

## Training (33–35)

Training is LMS-lite: catalog, sessions, quizzes and certificates, with progress shown as rings.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 33 | Catalog, course detail, learning paths | Search, filter pills, course cards, course detail (modules list, enroll button), learning path with stepper | Desktop 3-up cards; mobile 1-up | Empty: trainings empty state |
| 34 | My learning, session attendance, quiz | Progress rings, course list, session check-in (QR), quiz with radio groups, result screen with success ripple | Mobile-first | Quiz options keep their order across languages |
| 35 | Certificate (EN/AR) + verification page | Certificate frame artwork, name/course/date fields, QR code, verification page (valid / revoked states) | Certificate is A4 landscape; verification page mobile-first | AR certificate mirrors the layout; QR and serial stay LTR |

## Reports (36–37)

Reports reuse the colorblind-safe chart palette; every chart has direct labels or a legend.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 36 | Reports library + viewer | Report cards by category, viewer with filter bar, charts, data table, export | Desktop: chart + table. Mobile: charts stacked, table as cards | Charts never mirror; legends follow the reading direction. Empty: reports empty state |
| 37 | Custom report builder | Stepper (Dataset → Fields → Filters → Group → Visualize), field list with checkboxes, filter builder, chart type segmented control, live preview | Desktop-only (1280+) | Preview updates on each step; save as saved view |

## Settings and integrations (38–44)

Integrations are configured through wizards and watched by the health widget; a critical notification never relies on OpenWA alone without a warning.

| # | Screen | Components | Responsive | RTL, states, notes |
| --- | --- | --- | --- | --- |
| 38 | Roles & permissions matrix | Role list, matrix (modules × actions) of permission cells with scope (None / Own / Team / Department / Branch / All), override dot, sticky headers, diff-before-save dialog | Desktop-only (1280+); mobile read-only per role | Matrix columns run in the reading direction; first column sticks to the reading start |
| 39 | Organization | Branch list (search-first, paged) with geofence map, departments tree with drag, positions table, employment types table | Desktop: list + detail. Mobile: list → detail | Tree indents toward the reading end. Map controls stay LTR |
| 40 | WhatsApp setup wizard | Driver choice cards (Official, OpenWA, both); Official: masked credential fields, webhook URL + verify token copy fields, test connection, send test message, template sync table with approval badges; OpenWA: sidecar URL + API key, live QR pairing (waiting / connected / failed); Routing: per category driver + fallback chain with warning | Desktop wizard with side rail; mobile compact stepper | QR dissolves into a ripple + “Connected” badge on pairing; waiting dot pulses. Warning banner when a critical category uses OpenWA only |
| 41 | Integrations hub + Cognito detail | Integration cards (health badge, last success/failure, Test, Edit, Rotate, Logs), Cognito: sources list, generated webhook URL copy field, field-mapping table with sample payload viewer, submission log with replay | Desktop 2-up cards; mobile 1-up | Not configured: integrations empty state. JSON payload viewer stays LTR |
| 42 | Notification templates editor | Channel tabs (Email / WhatsApp), EN + AR editors side by side, variable chips, live preview (email + WhatsApp bubble) | Desktop split; mobile one language at a time | AR editor is RTL; variables `{{name}}` stay LTR tokens inside RTL text |
| 43 | Audit log | Filter bar (actor, module, action, date range), data table with diff expansion, export | Desktop table; mobile cards | Immutable; no destructive actions |
| 44 | Notification center + preferences | Notification items (read/unread), filter tabs, preferences matrix (category × channel toggles), quiet hours time range | Desktop: popover from the bell + full page. Mobile: full page | Empty: inbox empty state |

## Component inventory

Every component below is drawn on the Design system page in Light LTR, Light RTL, Dark LTR and Dark RTL.

| Component | Sheet | States shown | Main screens |
| --- | --- | --- | --- |
| Button (primary, secondary, outline, ghost, destructive; sm/md/lg) | Actions & inputs | default, hover, focus-visible, active, disabled, loading, error | All |
| Icon button, segmented control | Actions & inputs | default, hover, focus, disabled | 7, 22, 37 |
| Text input, textarea, select | Actions & inputs | default, hover, focus, filled, disabled, error, loading | All forms |
| Async searchable picker | Actions & inputs | open, results, loading row, load more | 13, 18, 19, 27 |
| Multi-select chips | Actions & inputs | chips, overflow +N | 2, 16, 24 |
| Date range picker (Gregorian + Hijri), time picker | Actions & inputs | range, weekend, focus | 11, 21, 24 |
| File dropzone, signature pad | Actions & inputs | idle, uploading, error; signed | 2, 9, 31, 32 |
| Toggle, checkbox, radio, slider | Actions & inputs | on/off, focus, disabled, indeterminate, error | 2, 17, 44 |
| Sidebar + branch switcher, top bar, command palette | Navigation | expanded, collapsed, hover, active, open switcher | 3 |
| Bottom tab bar + FAB | Navigation | active, badge | 3 (mobile) |
| Tabs, pill tabs, breadcrumbs | Navigation | active, hover, focus, count, disabled, overflow, collapsed | 7, 8, 39 |
| Wizard side rail, compact stepper | Navigation | done, current, skipped, upcoming | 2, 9, 37, 40 |
| Data table + filter bar + saved views | Data display | sorted, selected, hover, expanded, bulk bar, paging | 7, 16, 26, 28, 43 |
| KPI card with sparkline | Data display | up, down, neutral | 5, 6 |
| Status badge (8 workflow states), integration health badge | Data display | all states | 12, 13, 28, 41 |
| Avatar, presence, avatar stack, default avatar | Data display | sizes, +N | All |
| Workflow timeline, comment thread | Data display | done, current (SLA), upcoming | 12, 13, 28 |
| Approval card (+ swipe) | Data display | pending, swiping | 5, 13 |
| Notification item | Data display | unread, read | 5, 44 |
| Calendar cell, shift block | Data display | today, holiday, selected, weekend; conflict, open, dragging | 21–24 |
| Kanban card, permission matrix cell, policy source chip | Data display | override, inherited chain | 19, 29, 38 |
| Toast, dialog, drawer, bottom sheet, popover, tooltip | Overlays & feedback | success, error, info, progress | All |
| Empty state, progress (linear, ring) | Overlays & feedback | determinate, indeterminate, over-limit | All lists, 34 |
| Copy field, masked secret, test connection, alert banner | Overlays & feedback | copied, revealed, idle, testing, success, failure | 2, 40, 41 |
