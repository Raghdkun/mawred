# Phase 3a demo script: Forms and the rules language

About 15 minutes. Uses the demo company (see [phase-1.md](phase-1.md) for setup). Sign in as `hr.admin@mawred.test`.

## 1. Build a form (handoff 14, desktop 1280+)
1. **Forms → New form**: code `TRIP`, name *Business trip* / *رحلة عمل*. The builder opens with an empty draft.
2. From the palette (reading-start side) add **Short text** "Destination" (Required), **Single choice** "Kind" with *Client visit* / *Training*, **Short text** "Client name", **Date range** "Dates", **Calculated** "Nights".
3. Select *Client name* → **Visibility → Only show when…** Kind *is* Client visit. Select *Nights* → Calculation **Days in a date range** → Dates.
4. Labels have EN and AR side by side. Drag fields to reorder, or focus a handle and use space and the arrow keys.
5. **Preview**: switch EN/AR and phone size. **Save draft**, then **Publish** (v1). Edit again: the badge shows *Unpublished changes*; v1 stays frozen until you publish v2.

## 2. Fill it in and look at submissions
1. **Fill in**: Client name appears only for client visits; Nights updates as you pick dates; the Hijri date shows under each date. Leave Destination empty → inline error. **Send test submission**.
2. **Submissions**: filter by source; open one. Answers are labelled from the version they were made against; opening one and downloading files are audited.

## 3. Public link (D53)
1. **Settings → Public link**, link `trip`. Pickers aren't allowed on public forms (try adding an Employee field).
2. Open `/f/trip` in a private window: company logo, "powered by Mawred", language toggle. The ALTCHA check starts as soon as you type and is usually done by **Send** (about 3–4 s of browser work).
3. Bots: the hidden honeypot, a minimum fill time (3 s) and per-IP limits apply; a solved check can't be replayed. A closing date shows a closed notice.

## 4. Rules language (ADR-0008)
- `mawred.rule/v1` runs in PHP and TypeScript; `tests/fixtures/rules/*.json` and `tests/fixtures/forms/resolution.json` run in both Pest and Vitest.
- Advanced users can edit any condition as JSON (Edit as rule JSON); it's checked as you type.
