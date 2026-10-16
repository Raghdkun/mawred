# Phase 5b-1 demo script: Roster and attendance

About 20 minutes. Uses the demo company (see [phase-1.md](phase-1.md)); keep a queue worker running (`composer dev`). Set a branch's map area under **Organization → Branches** first (latitude, longitude, radius) so the location check has something to compare against.

## 1. Shifts and rotations
1. As an HR admin, **Schedule → Shifts and rotations**: add Morning 07:00–15:00 and Night 22:00–06:00 (an end before the start runs into the next day), each with a break and a colour.
2. Add a rotation such as Morning, Morning, Night, Off.

## 2. Planning (handoff 22, D72, D73)
1. As **branch.manager@mawred.test**, **Schedule → Roster planner**. Switch Week / 2 weeks / Month; the week starts after the branch weekend; weekends and public holidays are shaded; time off shows in the person's row.
2. Click an empty cell to add a shift (a template or custom times). Drag shifts between people and days. Conflicts (overlap, time off, too little rest, weekly hours, too many days in a row) show on the block in words.
3. **Copy last week**, **Apply rotation** to a few people, set **Coverage targets**: the coverage row turns red on short days.
4. Everything is a draft until **Publish**; the bar counts what isn't published. People get one message each.
5. Add a shift in the **Open shifts** row and publish it.

## 3. My schedule (handoff 23)
1. As **employee@mawred.test**, **Schedule**: the month and an agenda of your published shifts (drafts never show).
2. **Ask for this shift** on an open shift; as the manager, open it and **Give shift**. With "give it to the first person who asks" on, the first claim wins.
3. **Ask to swap** on a shift: the colleague accepts first (an inbox task), then the manager; the roster swaps on approval.

## 4. Check-in (handoff 25, D26, D68, D70)
1. **Attendance** on a phone: the chip says whether you're inside the branch area. Tap **Check in**; the button turns into **Check out** and the punch is listed.
2. Outside the area with the policy on *flag*, a reason is asked for and the punch is flagged; on *block*, the button is off.
3. Turn the phone's network off and check out: it's saved on the phone and sent with its tap time when you're back online. A phone clock off by more than 5 minutes is flagged.
4. As an HR admin, **Attendance → Kiosks → Add kiosk**, open the one-time link on a tablet: a QR code changes every 20 seconds. On the phone, **Scan kiosk code** checks in.
5. Days without a shift expect the standard hours from policy; leave, holidays and the weekend expect nothing (D68).

## 5. The daily board (handoff 26, D69)
1. As **hr.officer@mawred.test**, **Attendance → Daily board**: present / late / absent / on leave with counts (tabs on phones) and everyone in a table.
2. Open someone: their punches for the day (flagged ones marked) and **Correct this day** with a reason (audited; replaced punches stay, struck through).
3. Overtime found in punches waits for approval: an Overtime request is raised for the person; approval sets how much counts. Rules that need no approval count it straight away.
4. **Monthly sheet** downloads the month as XLSX.
5. People can also send an **Attendance correction** request; approval applies it.

## 6. Install
On a phone, the browser offers to install Mawred; the **Check in** shortcut opens the check-in screen, and once opened it works offline.

## Checks
`composer test`, `composer test:browser` (roster, check-in, board, kiosk in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and dark ★ screens), `pnpm test`.
