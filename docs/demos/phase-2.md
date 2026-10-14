# Phase 2 demo script: Policy engine

About 15 minutes. Uses the demo company (see [phase-1.md](phase-1.md) for setup).

## Prepare
```bash
php artisan migrate:fresh --seed
php artisan db:seed --class="Database\Seeders\Demo\DemoSeeder"   # needs DEMO_PASSWORD in .env
pnpm build
php artisan serve
```
Sign in as `hr.admin@mawred.test` (`DEMO_PASSWORD`). The demo adds a *Riyadh HQ rules* set on the Riyadh branch, the US federal calendar on New York and a sample Saudi calendar on Riyadh.

## 1. Setup step 5 (handoff 2)
On a fresh install the wizard's **Policies** step is live: it shows the neutral default (21 annual days accrued monthly, 1.5×/2× overtime, 15-minute grace, 90-day probation) and can add the US federal holiday calendar for the first branch.

## 2. Policy sets and the editor (handoff 16, 17, 20)
1. **Policies → Policy sets**: the *Neutral default* (badge, applies to Global) and *Riyadh HQ rules* (Branch: Riyadh HQ). Export either as `mawred.policy-preset/v1` JSON; **Import preset** brings it back as a new set from a date.
2. Open *Riyadh HQ rules*. Sections run down the side; rules the set doesn't define say where people get them instead (work week and hours fall back to each branch's own record, D46).
3. Change *Late arrival grace* to 5 min. The save bar counts the change; pick a start date. A date in the past needs a reason.
4. **Save changes** opens the impact preview: "Affects N employees from …", each with before → after. **Save and apply**. Change *Values on* to before and after that date: history is kept, nothing is rewritten.
5. On a phone the editor is a read-only summary ("Edit on a larger screen").

## 3. Assignments and precedence (handoff 18)
1. **Assignments**: six cards in the reading direction, Global → Employee, with employment type and grade in one card (D30). Vertical on phones.
2. **Assign a set**: assign *Riyadh HQ rules* to a department or a single employee; the preview shows who changes. **End assignment** on a future one cancels it.

## 4. Effective policy and the explainer (handoff 19, profile)
1. **Effective policy**: pick a Riyadh employee. Every rule shows its value and a chip, e.g. *From Branch: Riyadh HQ*, with the orange dot when it overrides a less specific level. Open the chip for the full chain: the winner is filled, overridden levels struck through, plus a link to the set.
2. The same view is on the profile under **Effective policies**, and at `GET /api/v1/employees/{id}/policies?date=` (404 outside your people scope).
3. As `hr.officer@` (Riyadh only), a Dubai employee is not found.

## 5. Leave types and holidays (handoff 21, D47, D48)
1. **Leave types**: seven types from the neutral default. Add *Hajj leave*: a `leave.type.HAJJ` rule appears in every set's editor.
2. **Holidays**: pick *New York Office*, year 2026: Independence Day (Saturday 4 July) is observed on Friday 3 July. Pick *Riyadh HQ*: the sample Saudi calendar, with Eid blocks over the Friday–Saturday weekend; in the list, Hijri-based days show both Gregorian and Hijri dates. Assign the US calendar to Riyadh too (edit calendar → Observed by) and the same 4 July moves to Sunday 5 July, the nearest working day there.
3. **Import dates** on the Saudi calendar with a CSV (`date,name_en,name_ar,days,hijri_based`): all rows are checked first, and rows already there are skipped.

## 6. Arabic, dark mode
Switch to العربية: chains, chips, weekday chips (starting the day after the branch weekend) and units all follow the reading direction. Toggle dark mode on the editor.
