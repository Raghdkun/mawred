# Phase 8b demo script: Custom reports, sharing and schedules

About 10 minutes. Uses the demo company and a queue worker (`composer dev`). Builder screens need a desktop (1280 px or wider).

## 1. Build a report (D118, D121, handoff 37)
1. As **hr.admin@mawred.test**, **Reports** → **New report**.
2. **Dataset**: pick People (also Leave requests, Attendance days, Requests, Job applications, Training enrolments).
3. **Fields**: tick Name, Department, Hire date. The preview on the right fills in as you go.
4. **Filters**: add Status *is one of* Active; add Hire date *in the last* 365 days.
5. **Group**: group by Department, show *How many*. The preview turns into a bar chart.
6. **Visualize**: choose Bar or Line, the sort, name it "People by department" and **Save report**. It opens in the viewer with the usual filters, chart, table and **Export**.
7. Sensitive fields (date of birth, passport and residency expiry) are listed only for people with that field permission.

## 2. Share it (D119)
1. On the saved report, **Share** → add a person or tick the **Branch Manager** role → **Save sharing**.
2. As **branch.manager@mawred.test**, **Reports** lists it under Custom reports, "Shared by …". It shows their branch only: everyone sees a shared report with their own data, never the owner's.
3. Only the owner edits, shares or deletes it.

## 3. Schedule it (D120)
1. On any report you can export, **Schedule**: every day, week (pick the day) or month (pick the date), the hour, the file type, and for reports with dates, the period each run covers (e.g. Last month).
2. **Reports → Scheduled reports** lists them with the next run and how the last one went; **Pause** / **Resume** / delete.
3. `reports:schedules` runs every 15 minutes. Each file arrives in **My exports** with a notification. If the owner loses access, the schedule pauses and tells them why.

## Checks
`composer test` (builder access, preview and validation, saving, sharing in each viewer's scope, hidden sensitive fields, exports of saved reports, schedules running on time, once, with relative periods, pausing on lost access, deleting a report with its schedules; `ScheduleClock` unit cases), `composer test:browser` (build and save, share, schedule and pause, EN + AR with axe; `SCREENSHOTS=1` for the sweep and dark mode), `pnpm test`.
