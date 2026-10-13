# ADR-0010: Policy engine — typed keys, layered partial sets, effective dating, ledgers

- Status: Proposed
- Date: 2026-10-13

## Decision
- **Typed key registry** (`Domain/Policies/Keys/*`): each key declares an id, a value Data class (validation), a default, a UI editor and the domains it affects (leave, attendance, scheduling, overtime). The value types include components: `AccrualRule {period, rate, proRata, rounding, cap, startAfter(probation?)}`, `OvertimeRule {thresholds, multipliers{weekday, weekend, holiday, night}, rounding, cap}`, `GraceRule`, `RestRule`, `WeeklyMaxRule`, `HourlyPermissionQuota`. No formulas in strings.
- **Sets and assignments**: a policy set holds a subset of keys. Assignments target `global < branch < department < position < grade < employment_type < employee` (employee highest). **Employment type beats grade** (D13). The UI draws the chain as the designed **6 levels**: the "Employment type / grade" card contains both ordered sub-levels, and the explainer chip names the exact one (D30).
- **Effective dating**: `policy_values (set_id, key, value jsonb, effective_from, effective_to)` and assignments with the same columns. Changes close the old row and insert a new one, never update in place. Back-dating is allowed only with a reason and triggers recalculation from that date.
- **Resolver**: `PolicyResolver::for(Employee, LocalDate)->get(key)` returns `ResolvedValue {value, sourceSet, level, target, effectiveFrom}`. A request-scoped memo plus a Redis cache keyed by `(employee, date, policyEpoch)` is invalidated by bumping `policyEpoch`.
- **Ledgers**: leave and hourly permission use append-only ledgers. Balances are a projection. Attendance day results are recomputed from raw events plus the schedule plus policy.
- **Recalculation and impact preview**: the pure calculators (`AccrualCalculator`, `AttendanceCalculator`, `OvertimeCalculator`) are run for the affected employees and dates and diffed against the stored system entries. The preview shows the diff; applying it posts reversals plus new entries in a chunked, queued job tagged with `calc_run_id`.
- **Holidays** (D12): rule types are `fixed` (month/day), `nth_weekday` (e.g. 4th Thu of Nov, last Mon of May) and `one_off`, with an `observed` shift rule (Sat→Fri, Sun→Mon, or none). There is **no Hijri-recurring type**: Islamic holidays are one-off entries or CSV. A one-off holiday can carry a `hijri_based` flag so the calendar shows both dates (handoff screen 21). Hijri *display* is optional otherwise.
- **Presets**: `mawred.policy-preset/v1` JSON import/export, validated against the key registry, with a semantic version. The shipped "Neutral default" preset is clearly labelled. A separate **"US Federal holidays"** holiday-calendar preset ships too.

## Consequences
- + Explainable values (provenance), history never rewritten, safe "what-if" previews.
- − More rows, and calculators must stay pure (enforced by tests that run them twice and compare).
