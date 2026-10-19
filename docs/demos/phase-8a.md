# Phase 8a demo script: Reports and exports

About 10 minutes. Uses the demo company (`php artisan migrate:fresh --seed` with the demo seeders) and a queue worker (`composer dev`), so exports are made in the background as they would be in production. The demo company has hiring, training, requests and people data; attendance and leave reports fill as people check in and take leave, so pick a wide period for those.

## 1. The library (D112, handoff 36)
1. As **hr.admin@mawred.test**, open **Reports** in the sidebar: reports grouped by People, Leave, Time and attendance, Requests, Hiring, Training, Payroll and Administration.
2. As **branch.manager@mawred.test**, the same page lists the reports for their branch's modules; the audit log isn't there.

## 2. A report (D117)
1. Open **Headcount**. The filter bar (date, branch, department) is in the address, so a view can be bookmarked. Headline numbers, a chart in the colourblind-safe palette (it never mirrors in Arabic; the legend follows the reading direction), and the table (cards on a phone).
2. Open **Hiring funnel** with a wide period: applicants by how far they got, per requisition. **Time to hire** shows days from application, and from opening the requisition, to the hire.
3. As the branch manager, the same reports show their branch only. Every report has a scope-leak test.

## 3. Payroll input (D7, D116)
1. Open **Payroll input**, choose a month and a branch: per person, working days employed, absences, lateness, unpaid leave in the month, overtime that counts (approved or automatic) by kind of day with its weighted hours, and allowances in force with what started, changed or ended.
2. Allowance amounts show only to people who can read salaries (HR admin); others see the allowance names.

## 4. Exports (D113, D114)
1. On any report, **Export** → Excel, CSV or PDF. The file is made on the `reports` queue with the filters on screen; **My exports** shows it waiting, then ready, and a notification links to it (no file is ever attached to a message).
2. **Download**: only the person who asked can, for 7 days; the download, the export and the request are in the audit log. Arabic exports are right-to-left; CSV opens in Excel with Arabic intact.
3. As HR admin, **Audit log** (Administration) exports who did what for a period (changed fields, never their values).

## Checks
`composer test` (every report scoped for a branch manager against HR, grouped counts, payroll-input arithmetic, the library and viewer, exports, owner-only download, CSV safety, limits, access lost before the file is made, pruning), `composer test:browser` (open a report and export it; filter a report on a phone, EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and dark mode), `pnpm test`.
