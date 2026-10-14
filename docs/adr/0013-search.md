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

## Implementation notes (Phase 1)
- `App\Support\Search\GlobalSearch` with per-domain `SearchProvider`s (employees, branches, departments); a provider runs only when the viewer has its ability, and each one goes through its `ScopedQuery`.
- Matching uses the normalised `search_text` (trigram index) and ranks by `similarity()`. Local phone formats match E.164 numbers; national IDs match only exactly through the blind index, and only for viewers with field access.
- `tsvector` full-text search is not needed yet at this data size; it can join behind the same interface when requests and candidates arrive.

