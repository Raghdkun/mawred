# Phase 5b-2 demo script: Calendar, meetings and booking

About 15 minutes. Uses the demo company (see [phase-1.md](phase-1.md)); keep a queue worker running (`composer dev`) so invitations go out. Mailpit (port 8025) shows the emails.

## 1. My calendar
1. As **employee@mawred.test**, open **Calendar**: a week grid with meetings, your published shifts shaded behind them, time away and public holidays in the all-day row. **Month** shows the whole month; on a phone it's an agenda.
2. Click a meeting: when, where, who and their replies, **Add to my calendar (.ics)**.

## 2. Finding a time (handoff 27, D76)
1. As **branch.manager@mawred.test**, **Calendar → Find a time**. Add two colleagues and pick a length.
2. The grid shows each person's working time (their shift, or their standard hours), busy blocks and time away for one day; arrows move the day. You see titles only for people your calendar scope covers, and never for private meetings.
3. **Suggested times** lists slots where everyone is free over the next days. Click one, give it a title, set a repeat if you like (every week on Sunday and Tuesday, until a date) and **Save and send**.

## 3. Invitations and replies (D75)
1. Each invitee gets the invitation in the app and by email with **Going / Maybe / Not going** buttons and an `.ics` attachment. One tap records the reply without signing in.
2. With WhatsApp set up, the invitation arrives with the same three buttons; a tap from the invitee's own number records the reply.
3. Declined meetings stay on the invitee's calendar (struck through) but no longer count as busy.

## 4. Changing a repeating meeting (D78)
1. Open one occurrence as the organiser → **Edit**: choose *Only this one*, *This and the ones after* or *All in the series*. Everyone hears about the change.
2. **Cancel meeting** offers the same three choices.

## 5. Booking (D77)
1. As someone with `calendar.update` (managers, HR), **Calendar → Booking pages → New booking page**: name it (e.g. Office hours), 30-minute slots, Sunday 10:00–12:00, open to a branch.
2. As an employee of that branch, **Calendar → Book time**: open the page, pick a time, add a note, book. It lands on both calendars and the owner is told. Taken slots and the owner's other meetings disappear from the list.

## Checks
`composer test`, `composer test:browser` (find → invite, reply, book, calendar screens in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep), `pnpm test`.
