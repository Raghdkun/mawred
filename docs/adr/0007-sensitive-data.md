# ADR-0007: Encrypting sensitive fields at rest

- Status: Proposed
- Date: 2026-10-13

## Decision
- Laravel `encrypted` / `encrypted:array` casts (AES-256-CBC + MAC via APP_KEY) cover national ID, passport and residency numbers, bank name/IBAN/account, salary components, and any custom field flagged sensitive.
- Blind index: `<field>_bidx` = HMAC-SHA256(normalised value, `BLIND_INDEX_KEY`), with an index. It is used for import matching and duplicate detection. Encrypted fields are never sortable and never range-filterable.
- Key rotation: set `APP_PREVIOUS_KEYS`, deploy, then run `php artisan mawred:reencrypt` (chunked, resumable, progress over Reverb, covers integration credentials and every encrypted column). Rotating `BLIND_INDEX_KEY` requires `mawred:reindex-blind`. Both procedures are in the runbook.
- Salary history is field-permissioned and audited on read.
- Backups contain ciphertext. Losing APP_KEY means losing that data, so APP_KEY is escrowed separately from the backups (runbook).
