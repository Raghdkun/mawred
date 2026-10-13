# ADR-0018: Monthly range partitioning for high-volume append-only tables

- Status: Proposed
- Date: 2026-10-13

## Decision
- Partitioned by month on their time column: `attendance_events (occurred_at)`, `activity_log (created_at)`, `notification_deliveries (created_at)`, `message_logs (created_at)`, `webhook_receipts (received_at)`.
- They are created via raw SQL in migrations (`PARTITION BY RANGE`), with PK `(id, <time col>)` and a default partition as a safety net.
- `php artisan partitions:maintain` runs daily: it creates the next 3 months and, per retention setting, detaches partitions past retention, exports them to the backup disk, then drops them.
- Laravel models use these tables normally. FKs **to** partitioned tables are avoided; references are soft (id + time).
- pg_partman is not used (it would need a custom Postgres image). Revisit if maintenance grows.

## Consequences
Fast pruning and time-bounded queries, and cheap retention. Queries must include the time range; `Queries` classes enforce it.
