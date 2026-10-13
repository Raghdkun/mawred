# ADR-0013: Global search on PostgreSQL (FTS + pg_trgm), scope-enforced

- Status: Proposed
- Date: 2026-10-13

## Decision
- Search covers employees, candidates and requests (Q: add job postings and courses later).
- Each searchable table has a generated `search_text` column (Arabic-normalised: tashkeel and tatweel removed; أ/إ/آ→ا, ى→ي, ة→ه; lower-cased Latin; digits unified), a `tsvector` built with the `simple` config, and a GIN `pg_trgm` index for fuzzy and partial matches (names, employee numbers, phone, request refs).
- Queries go through `GlobalSearch`, which unions per-type `ScopedQuery` results. Each type is limited and ranked, so out-of-scope rows are never fetched.
- Encrypted fields are never searchable except through exact blind-index match by users who hold the field permission.

## Alternatives
Meilisearch or Typesense via Scout: better relevance, but it adds a service, a data copy and a **second place where scope must be enforced**. Rejected for v1. It can be added later behind the `GlobalSearch` interface if relevance needs it.
