# Phase 8c demo script: Role dashboards

About 5 minutes. Uses the demo company. Dashboards load just after the page; numbers count up and charts draw in (instantly with reduced motion).

## 1. HR admin home (handoff 6, D115, D123)
1. As **hr.admin@mawred.test**, open **Home**: four KPI cards with 12-month sparklines: headcount (and the change since last month), turnover over 12 months, open positions, requests waiting. Each card opens its report.
2. **Attendance today** by branch (each branch on its own date): in, late, on leave, absent, not in yet.
3. **Documents expiring**: expired or expiring in the next 30 days, with **See all** into the report.
4. **Integrations**: each connection's health; when one is disconnected, a red banner sits above everything.
5. As **hr.officer@mawred.test**, the same dashboard shows their branch only.

## 2. Manager home (handoff 5)
1. As **branch.manager@mawred.test**: the approvals queue, plus **Your team today**: in, late, away and not in yet, who's away (and until when), and today's late arrivals with how late. **Open the attendance board** goes to the board.
2. Two KPI cards: your team, and late arrivals this week (with a daily sparkline).

## 3. Employee home (handoff 4)
1. As **employee@mawred.test**: the **Check-in** card first on phones (today's status, check-in time, today's shift) with Check in / Check out.
2. **Your agenda**: the next seven days of shifts, meetings, interviews, training sessions, time away and holidays.

## Checks
`composer test` (HR KPIs, expiring documents, attendance today and integrations, an HR officer kept to their branch, a manager's team today and nobody else's, the check-in card and agenda), `composer test:browser` (the three homes in EN + AR with axe and no sideways scroll on phones; `SCREENSHOTS=1` for the sweep, dark for the ★ HR and employee homes), `pnpm test`.
