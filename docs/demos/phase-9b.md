# Phase 9b demo script: Server, backups, monitoring and deploys

Nothing in 9b has run on the real server yet: that needs server access and the go-live values.
This walkthrough uses a local Docker build; the server steps are in `deploy/README.md`.

## 1. Health
- `curl -s localhost:8000/health/live` → `{"status":"ok"}` (no session, no cookies).
- `curl -s localhost:8000/health/ready` → each check and whether it passed. Stop Gotenberg: still
  200 but `degraded`. Stop Redis: 503 `down`, which takes an instance out of a deploy.

## 2. Backups and a restore drill (D130)
1. `docker build -f docker/app/Dockerfile -t mawred/app .` (the image now has `pg_dump` 18).
2. In a scratch compose project, `php artisan backup:run` writes an AES-256 archive to the
   `backups` volume; `deploy/restore-drill.sh <archive>` decrypts it, loads it into a throwaway
   Postgres and reports, for example, "Users: 9, employees: 115, migrations: 45".
3. With a wrong `BACKUP_ARCHIVE_PASSWORD` the drill fails loudly and emails `OPS_ALERT_EMAILS`.

## 3. Zero-downtime deploy with rollback (D132)
1. `deploy/deploy.sh <tag>`: a second `app` starts beside the first, turns ready, the old one is
   retired, workers move over; `deploy.sh status` shows the tags.
2. A release that crashes on start is caught within seconds and removed; the old one keeps serving.
3. `deploy.sh rollback` returns to the previous tag.

## 4. Monitoring (D131)
- `php artisan ops:alert "Disk 85% full" "…"` emails `OPS_ALERT_EMAILS` (suppressed addresses skipped).
- `deploy/monitor.sh` checks disk, memory, containers and pending reboots every 5 minutes.
- Errors go to GlitchTip, tagged with the request id from the `X-Request-Id` header.

## 5. The server (D133)
Walk through `deploy/harden.sh`, `deploy/go-live-checklist.md` and the runbooks.

## Checks
`composer test` (health, ops alerts, Reverb runtime config, deploy scripts: parse, executable,
shellcheck, timers), `composer test:browser`, `pnpm test`, plus the local image build, backup,
drill, rollout and broken-release runs described above.
