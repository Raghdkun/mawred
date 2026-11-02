# ADR-0019: Single-VPS Docker Compose deployment with zero-downtime rollout

- Status: Proposed
- Date: 2026-10-13

## Decision
- **Host**: Ubuntu 24.04 LTS on a Hostinger KVM VPS.
  - **KVM 4 (4 vCPU / 16 GB / 200 GB NVMe)**, decided (D20): ClamAV is on (~1.2–1.5 GB), plus Gotenberg Chromium, WAHA (GOWS, light), GlitchTip and SSR.
  - A memory budget is documented in `deploy/sizing.md`.
- **Services**: `app` (two blue/green slots, one live; D134), `horizon`, `scheduler`, `reverb`, `postgres` (18), `redis` (AOF on, separate DBs for cache/queue/session), `gotenberg`, `waha`, `clamav` (on by default, D20), `ssr`, and `glitchtip` (web + worker, sharing Postgres and Redis with a separate DB, D21). Each has a memory limit, a healthcheck, `restart: unless-stopped` and json-file log rotation.
- **Images**: built in GitHub Actions, pushed to GHCR, tagged with the git SHA. The server only pulls.
- **Rollout** (`deploy/deploy.sh`, run via SSH from Actions):
  1. pull the new images;
  2. `php artisan migrate --force` in a one-off container using the new image (migrations must be **expand-only** and backward compatible with the running code; "contract" migrations ship in a later release);
  3. start the new `app` container next to the old one (docker-rollout style) and warm caches (`optimize`, `event:cache`, `view:cache`, Wayfinder/ziggy build-time already);
  4. wait for `/health/ready` (DB, Redis, queue, storage, Gotenberg) to pass;
  5. nginx's upstream is pointed at the new slot and reloaded gracefully, and the old slot drains and stops (D134);
  6. `horizon:terminate` and restart `reverb` and `scheduler` on the new tag;
  7. a post-deploy smoke check. On failure, revert the tag and re-run the steps (migrations are forward-compatible, so no down-migration is needed).
- **Backups**: spatie/laravel-backup runs nightly (DB dump + private disk) to **Hostinger Object Storage** (D15). Because it is the same vendor as the VPS: archives are encrypted (backup password in env and escrowed offline), the bucket keys are write-only and can't delete, versioning/object-lock is used if available, and a quarterly copy goes to a second location named in the runbook, keeping 7 daily, 4 weekly, 12 monthly and 2 yearly copies. A monthly restore drill restores to a scratch Compose project and runs the smoke suite (runbook). Hostinger snapshots are an extra layer only; they keep one slot.
- **Monitoring**: Pulse (with its own DB connection), **GlitchTip** self-hosted with the Sentry SDK (D21; errors and performance, `request_id` correlation, PII scrubbing), a free external uptime ping (it can catch the whole host being down), `/health/live` and `/health/ready`, a cAdvisor-lite approach via `docker stats` + a node-exporter-free script that alerts on disk > 80 % and memory > 90 % through the notification system (email). An optional external uptime ping.
- **Hardening**: `deploy/harden.sh` sets up a non-root deploy user, SSH keys only, root login disabled, UFW 22/80/443, fail2ban (sshd; D133 dropped the proxy auth-failure jail), unattended-upgrades, a swap file, Docker log rotation and UTC.
- **Mail**: always through an external transactional provider. Hostinger throttles direct SMTP to 5/min anyway.

## Implementation notes (Phase 9b, D130–D133)
- Offsite copies go through an `rclone` container with the write-only key, not a Flysystem S3 disk; retention uses `daily/`, `weekly/`, `monthly/` and `yearly/` prefixes with bucket lifecycle rules, since the server can't delete (D130).
- The monthly drill restores into a throwaway Postgres and checks migrations and counts; the full smoke suite isn't run against it (D130).
- GlitchTip 6 runs as one container (web and worker); alerts are emailed through `php artisan ops:alert` rather than the notification system (D131).
- The Caddy auth-failure jail is dropped; fail2ban covers SSH and repeat offenders (D133).
- The scripts, timers and runbooks live in `deploy/` (see `deploy/README.md`).
- The edge is the host's nginx instead of a Caddy container, and the project lives in `/core/mawred` (D134). The app runs in two slots (`app-blue` on 127.0.0.1:8081, `app-green` on :8082); a deploy starts the idle one, points `.deploy/nginx-upstream.conf` at it and reloads nginx (the deploy user may run only `nginx -t -q` and `systemctl reload nginx` as root), then stops the other. `deploy/nginx-site.sh` writes the nginx site and gets certificates.
- The first server is shared with other sites (D135): `harden.sh --shared` sets up only Mawred's parts and leaves SSH, the firewall and Docker's settings to the server's owner.
