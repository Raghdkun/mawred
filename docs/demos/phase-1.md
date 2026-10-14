# Phase 1 demo script: Organization & People

About 20 minutes. Uses the demo company.

## Prepare
```bash
docker compose up -d
php artisan migrate:fresh --seed
php artisan db:seed --class="Database\Seeders\Demo\DemoSeeder"   # needs DEMO_PASSWORD in .env
pnpm build
php artisan serve
php artisan mawred:install --name="Nora Al-Harbi" --email="admin@mawred.test"
```
Demo sign-ins (local only, all with `DEMO_PASSWORD`): `hr.admin@`, `hr.officer@`, `branch.manager@`, `dept.manager@`, `team.lead@`, `employee@mawred.test`. HR roles enrol 2FA on first sign-in.

## 1. Setup step 4 and Organization (brief §5, handoff 2 and 39)
1. On a fresh install, the wizard's **Branch** step is now live: pick a country (time zone, currency and phone region follow), set the weekend and hours, click the map to drop the check-in pin and drag the radius.
2. **Organization → Branches**: search-first list; open *Riyadh HQ* for the detail with the read-only geofence map. Map controls stay LTR in Arabic; the radius slider mirrors.
3. **Departments**: drag *Logistics* onto *Sales*, then use **Move to…** from the row menu to move it back (keyboard path). Try moving *Operations* under its own child: refused.
4. **Positions** and **Types & grades**: create a grade with a rank; archive an employment type.

## 2. Directory (handoff 7)
1. **People**: table with sorting, column picker and compact density. Select three people → **Invite to Mawred** (skips anyone already with an account).
2. Filter by branch *Dubai Marina* and status *Everyone*; **Views → Save current view…** as "Dubai, all".
3. **Grid** and **Org chart**: the chart starts at the CEO and grows toward the reading end; expand *Operations*.
4. Switch to العربية: names, sort order and the chart all follow the language. Resize to a phone: cards, stacked filters.
5. Sign in as `branch.manager@`: only Dubai staff (plus the one person with a secondary placement there). Open a Riyadh profile URL directly: 404.

## 3. Profile (handoff 8)
1. Open any Riyadh store associate. **Job**: change placement from the 1st of next month (future-dated, shown as upcoming), add a secondary placement, hand over a laptop.
2. **Personal**: restricted details show only what the role may read. As HR admin edit the IBAN; as `hr.officer@` the bank group is not there at all. Every view lands in the audit log as `sensitive / viewed` (fields only, never values).
3. **Documents**: residency permits show warning (≤ 30 days) and danger (expired) badges; download is audited.
4. **History**: hire, placement change and salary rows; salary only for roles with salary access.
5. **More actions → Record leaving**.

## 4. Import (handoff 9)
1. **Import**: download the template (in your language), or upload any spreadsheet; columns map from English or Arabic headers.
2. **Check the file**: counts of new/updated/invalid rows, row errors and the XLSX error report. Managers in the same file are linked; loops are refused.
3. **Import N rows**: live progress, then the summary.

## 5. Search and settings
1. ⌘K: type a name in Arabic or English, a local phone number (`050…`), or (as HR) a national ID.
2. **People settings**: add a sensitive custom field (HR admin gets write automatically; grant others in Roles → Field access), change the employee number prefix, add a document type with reminder days.

## Checks behind the demo
`composer lint && composer analyse && composer test` · `composer test:browser` (EN + AR, axe) · `composer test:performance` · `pnpm lint && pnpm types:check && pnpm test && pnpm build`
