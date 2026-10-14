# ADR-0015: Integrations — credential vault, driver interfaces, routing

- Status: Proposed — unofficial driver = WAHA (D8); email = provider-agnostic SMTP (D9); Cognito greenfield (D19)
- Date: 2026-10-13

## Credential vault
- Phase 3b ships the `integration_connections` table, encrypted credentials and the runtime `mawred` mailer for setup step 7 (email). Phase 4 adds the other connection types, health checks, the hub and `mawred:reencrypt`.
- `integration_connections (ulid, type, name, config jsonb, credentials text encrypted:array, status, last_success_at, last_failure_at, last_error, health_checked_at)`.
- Data objects return masked credentials (`••••last4`). Updates replace fields and never echo values back.
- Every change and rotation is audited (who, what key, but never the value).
- `php artisan mawred:reencrypt` re-encrypts after an `APP_KEY` rotation (with `APP_PREVIOUS_KEYS`). The runbook is in `deploy/runbooks/rotate-app-key.md`.

## Cognito Forms (verified constraints, 2026-10)
- "Post JSON Data to a Website" is on the Pro/Team/Enterprise plans. **Each event (submit, update, delete) has its own endpoint URL**, and **custom headers are not supported**.
- Each source therefore gets three URLs, `/hooks/cognito/{source_token}/{submit|update|delete}`, where `source_token` is 40+ random chars (stored hashed) and can be rotated from Settings. **The "optional shared-secret header" in the brief is not possible.** The secret lives in the URL path instead. The UI says so, and the URL is shown once with a copy button.
- Every payload is stored raw (`cognito_receipts`, partitioned). Processing is queued. The endpoint returns 200 quickly, because Cognito retries up to 15 times over 72 h on 4xx/5xx; 404/410/413 are not retried.
- **Idempotency key = source + Entry.Id + event + Entry.Version (or a payload hash if no version is present).** Entry.Id + event alone would drop legitimate second and third updates. (The brief said "entry ID + event type"; this is a deliberate refinement.)
- Cognito file links expire in about 30 minutes, so the processing job downloads attachments into medialibrary immediately.
- Employees are resolved by email or employee number (mapping config), and then the mapped workflow starts. Update events update the instance's submission data while it is still in an editable state; otherwise they are logged as "late update". Delete events cancel the instance if it is still running.
- The field-mapping UI has a "capture sample payload" mode, which stores the next receipt as the sample.

## WhatsApp — `WhatsAppChannel` interface
- Interface: `send(Message): SendResult`, `health(): Health`, `supportsInteractive(): bool`, `templates(): ?TemplateCatalog`.
- **Official (Cloud API, Graph v25/v26 pinned in config)**:
  - Inbound webhook: GET handshake with the generated verify token, and POSTs verified with `X-Hub-Signature-256` (HMAC-SHA256 of the raw body with the app secret, constant-time compare). Deduplication on message/status id, because Meta retries for up to 36 h.
  - **Interactive reply buttons (≤ 3, ≤ 20-char titles) only work inside the 24 h customer-service window.** Approval requests usually fall outside it, so they are sent as **approved Utility templates with quick-reply buttons** whose payload carries the action token id. The router picks interactive vs template automatically from the stored window state per contact.
  - Template sync uses `GET /{WABA_ID}/message_templates`. Template variables are mapped from workflow data in the template editor.
  - Status tracking covers sent, delivered, read and failed (with error code).
  - Throughput: 80 msg/s by default, handled by the shared rate limiter.
- **Unofficial driver ("OpenWA")**:
  - A sidecar on the private network only, memory-capped, API key auth.
  - QR pairing is streamed to the wizard over Reverb. Session status comes from the sidecar's webhook plus a health poll.
  - Approvals always fall back to **signed links**. A persistent in-app warning explains the ban risk.
  - **WAHA** (`devlikeapro/waha`, Apache-2.0) with the GOWS engine (fallback: NOWEB). Pairing uses `GET /api/{session}/auth/qr` and the `session.status` webhook (STARTING, SCAN_QR_CODE, WORKING, FAILED, STOPPED). It is pinned to a tested image tag and memory-capped at 512 MB. The driver class is `WahaDriver`. The UI label is **"Unofficial — WhatsApp Web (WAHA)"** wherever the handoff says "OpenWA" (D31). Alternatives considered: open-wa/wa-automate (non-OSI Hippocratic licence, Chromium-heavy) and rmyndharis/OpenWA (MIT, 0.x).
- **Router**: one chain per notification category (e.g. Official → OpenWA → Email). Critical categories default to Official or Email, and the UI warns when one is set to OpenWA only. Phone numbers are normalised to E.164 (libphonenumber), users opt in per channel, and each attempt is written to `message_logs`.

## Email
- The SMTP or transactional provider credentials are stored in the vault. A runtime mailer `mawred` is built from them, with a cached config invalidated on save. The wizard can send a test message.
- Provider-agnostic SMTP (D9). A `BounceWebhookDriver` interface is in place, and the first concrete driver is added once a provider is chosen.

## Hub and health
- A scheduled `integrations:health` job runs every 5 minutes and evaluates each connection. Status changes (healthy, degraded, disconnected, not configured) raise events, which produce an in-app and email alert to the integration admins plus a dashboard widget.
- Webhook failure-rate spike detection uses a rolling window over the receipts tables.
