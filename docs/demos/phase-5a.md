# Phase 5a demo script: Leave

About 15 minutes. Uses the demo company (see [phase-1.md](phase-1.md)). Run `php artisan leave:accrue` once (and a queue worker, `composer dev`) so balances are posted; it runs nightly at 02:30. Fresh installs get the half-day option on the leave forms; on an older database run `php artisan migrate:fresh --seed`.

## 1. Balances (D63)
1. As **employee@mawred.test**, open **Leave**. One card per leave type for this calendar year: what's available, out of what was granted, days waiting for approval and any carry-over about to expire. The ring shows what's left.
2. **Statement** lists every ledger line oldest first with a running balance: earned (monthly accrual), carried over (with its expiry), carry-over expired, adjustments, leave taken and given back.
3. Switch the year to see last year's statement.
4. **Home** shows the first three balances.

## 2. Requesting leave (D64, D65)
1. **Request leave → Annual leave**. Pick dates: the aside shows the working days it takes (weekends and the branch's holidays don't count), what's available on the first day (including accrual earned by then) and what's left after. A single day offers morning or afternoon only.
2. Ask for more than is available, dates that clash with your other time off, a blackout period or dates across 31 December: the aside explains why, and sending is refused with the reason on the dates field.
3. Send a valid request: the days are held straight away (the card shows them as waiting). The request page and the approver's inbox show what it takes and the balance.
4. Approve it as the manager and HR: the hold becomes *Leave taken* on the statement. Reject or withdraw instead and the days come back. Return it for changes and resend with other dates: the hold moves.
5. **Sick leave** longer than the rule allows without a document (Policies → leave rule *Document needed for more than*) needs the medical note attached.
6. **Day off or hourly permission → A few hours**: at most the per-request limit, and the month's quota (Policies → Hourly permissions) shows as *Left this month*.

## 3. The leave calendar (handoff 24)
1. **Leave → Leave calendar**: the month grid starts after the branch weekend; weekends and public holidays are shaded; approved and waiting time off are told apart by fill and a dashed border (and in words for screen readers). Days with more than three people show **+N more**.
2. Filter by branch or department (with the departments under it). Everyone sees only the people their leave scope reaches.
3. On a phone the grid becomes an agenda of the days with someone away.

## 4. HR tools
1. As **hr.officer@mawred.test**, open a person → **Leave** tab: the same balances and statements, plus **Adjust balance** (positive or negative, with a reason; audited) and **Cancel leave** on approved time off (the days return).
2. As **hr.admin@mawred.test**, **Leave → Blackout periods**: add dates when leave can't be requested, for everyone, a branch or a department, and for all or some leave types. Requests already sent stand.
3. **Policies → a set → Annual leave rules**: change the entitlement and preview: each affected person shows the days earned this year before, after and the difference. Increases count towards new requests at once; the nightly run posts the difference (down as well as up).

## Checks
`composer test` (leave engine scenarios, HTTP and scope-leak tests), `composer test:browser` (leave journeys in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep), `pnpm test`.
