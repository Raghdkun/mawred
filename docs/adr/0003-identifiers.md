# ADR-0003: bigint internal keys, ULID public identifiers

- Status: Proposed
- Date: 2026-10-13

## Context
The brief requires every externally exposed ID to be a ULID. The high-volume tables (attendance events, audit, deliveries) are partitioned, and partitioned PKs must include the partition key. ULID primary keys as `char(26)` make every FK and index about 3× larger than `bigint`, and packages that use morph keys (permission, activitylog, medialibrary) need extra configuration for non-integer keys.

## Decision
- Every table has `id bigint generated always as identity` as its PK, used for FKs and joins.
- Business tables get a `ulid char(26) unique not null` column, generated on create by a `HasPublicUlid` trait. `getRouteKeyName()` returns `ulid`.
- API Resources and Data objects expose `id` = the ULID. The internal `id` is never serialised. An arch test plus a serialisation test check that no model in `$hidden` misses `id`, and a Pest dataset over all Resources asserts that no integer `id` appears.
- Inputs that reference other records (`branch_id` in a request) accept ULIDs. A `UlidExists` rule resolves them to internal ids inside the Data object.
- Pure-internal tables (pivots, ledgers, partitions) don't need a ULID unless they are exposed.

## Consequences
- + Small indexes, fast joins, default package compatibility, partition-friendly.
- + Records can't be enumerated, and creation order can still be read from the ULID.
- − Two identifiers per row, and developers must never leak `id`. That rule is tested.

## Alternatives
- ULID as PK everywhere: simpler mentally, but larger indexes and package morph config.
- UUIDv7 in a native `uuid` column (16 bytes): compact, but the brief says ULID. Can be revisited.
