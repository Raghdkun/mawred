# ADR-0004: Instants in UTC, calendar dates branch-local

- Status: Proposed
- Date: 2026-10-13

## Context
Branches can sit in different timezones and have different weekends. Leave and roster days are calendar concepts. Converting them to UTC instants creates off-by-one-day bugs, especially for overnight shifts and DST zones.

## Decision
- **Instants** (`timestamptz`, app timezone `UTC`): punches, approvals, created_at, SLA due times, event start/end.
- **Local dates** (`date`, no tz): leave days, roster dates, holidays, `effective_from/to`, attendance day keys, birthdays, contract dates. They are interpreted in the subject's branch timezone.
- **Local wall-clock times** (`time`): shift template start/end, working hours, quiet hours (interpreted in the branch or user tz).
- The `Support\Time\BranchClock` service handles `today(Branch)`, `toLocalDate(instant, Branch)`, `startOfLocalDay(date, Branch)` and resolves overnight spans. Raw `now()->toDateString()` is banned in domain code (enforced by an arch test).
- An overnight shift belongs to the local date it **starts** on. Attendance-day computation uses the shift window plus a configurable early/late tolerance, not midnight.
- Display: the frontend gets ISO instants plus the display tz (user preference → own branch tz). Formatting uses `date-fns` + `@date-fns/tz` and the `Intl` calendar `islamic-umalqura` when Hijri display is on.
- A user's or employee's "home" timezone is their current assignment's branch tz.

## Consequences
- + No date drift, correct overnight handling, multi-timezone ready.
- − Developers must choose the right type. A PR checklist item and the arch test enforce this.
