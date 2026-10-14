# Architecture Decision Records

Format: Context → Decision → Consequences → Alternatives. Status: Proposed → Accepted / Superseded.

| # | Title | Status |
|---|---|---|
| [0001](0001-modular-monolith.md) | Modular monolith with domain folders | Proposed |
| [0002](0002-actions.md) | Actions are the single write path | Proposed |
| [0003](0003-identifiers.md) | bigint internal keys, ULID public identifiers | Proposed |
| [0004](0004-time-and-timezones.md) | Instants UTC, calendar dates branch-local | Proposed |
| [0005](0005-authorization-and-scopes.md) | RBAC + data scopes + field permissions (ScopeResolver) | Proposed |
| [0006](0006-audit.md) | Audit trail | Proposed |
| [0007](0007-sensitive-data.md) | Sensitive field encryption + blind index | Proposed |
| [0008](0008-form-schema.md) | Mawred Form Schema + shared rules language | Accepted |
| [0009](0009-workflow-engine.md) | In-house workflow engine | Accepted |
| [0010](0010-policy-engine.md) | Policy engine (typed keys, layered sets, effective dating, ledgers) | Accepted |
| [0011](0011-i18n-rtl.md) | react-i18next; RTL via logical properties | Proposed |
| [0012](0012-api-design.md) | REST API v1 conventions | Proposed |
| [0013](0013-search.md) | Global search on PostgreSQL, scope-enforced | Proposed |
| [0014](0014-pdf-gotenberg.md) | PDF via Gotenberg | Proposed |
| [0015](0015-integrations.md) | Integrations: credential vault, drivers, routing | Proposed |
| [0016](0016-notifications-templates.md) | Notifications + logic-less templates | Accepted |
| [0017](0017-runtime-and-proxy.md) | FrankenPHP (classic) + Caddy edge | Proposed |
| [0018](0018-partitioning.md) | Monthly partitioning | Proposed |
| [0019](0019-deployment.md) | Single-VPS Compose deploy, zero-downtime | Proposed |
| [0020](0020-frontend-data-and-libs.md) | Frontend data flow & libraries | Proposed |
| [0021](0021-spreadsheets.md) | XLSX/CSV via OpenSpout | Proposed |
| [0022](0022-money-and-currency.md) | Money and multi-currency | Proposed |
