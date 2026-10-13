# ADR-0006: Audit trail

- Status: Proposed
- Date: 2026-10-13

## Decision
- spatie/laravel-activitylog is the store. An `Auditable` trait on business models logs created/updated/deleted/restored with old and new values. Sensitive attributes are masked (`••••`, plus a "changed" flag).
- Extra columns: `actor_type` (user/system/integration/token), `impersonator_id`, `channel` (web/api/email_link/whatsapp/cognito/system), `ip`, `user_agent_hash`, `request_id` (also sent to Sentry).
- Explicit events cover: login, logout and failed login; 2FA changes; impersonation; sensitive-field reads; exports (with filter summary and row count); credential changes and rotations; every workflow transition; and out-of-app approvals.
- The table is partitioned monthly (ADR-0018). Retention is configurable (default 7 years for audit). Old partitions are detached and archived to backup storage.
- The audit is append-only for the application. The DB role used by the app has no `UPDATE`/`DELETE` on `activity_log`; partition maintenance uses a separate role in the maintenance command.
- The Auditor role reads audit and everything else read-only, with `all` scope but no sensitive fields unless granted.

## Consequences
High write volume, which partitioning and an async "log after response" pipeline mitigate for read audits. Writes are still logged synchronously inside the transaction.
