# ADR-0022: Money and multi-currency

- Status: Proposed
- Date: 2026-10-13

## Context
Branches span MENA and other countries (D2), so salaries, allowances, offers and payroll-input exports are in different currencies.

## Decision
- A `Support\Money` value object stores `amount_minor` (bigint) + `currency` (ISO 4217 char(3)). An Eloquent cast maps it to two columns. Minor units come from ISO 4217 (e.g. KWD/BHD/OMR have 3 decimals, JPY 0).
- Each branch has a `default_currency` and a `default_phone_region`. New salary and offer amounts default to the branch currency, but always store their own currency.
- Formatting uses `Intl.NumberFormat` (client) and `NumberFormatter` (server) with the viewer's locale and digit preference.
- **No FX conversion in v1.** Reports and exports group money by currency and never sum across currencies. The payroll-input export is per branch and currency.
- Encrypted salary fields store the serialized Money (ADR-0007).
