# Deploying Mawred

One Hostinger KVM 4 (Ubuntu 24.04) runs everything with Docker Compose (ADR-0019). GitHub Actions
builds the image; the server only pulls it. Nothing here runs anywhere until someone with server
access does it.

## On the server

| Path | What |
|---|---|
| `/srv/mawred/compose.prod.yaml`, `docker/caddy/`, `deploy/` | The deploy bundle. CI sends it before each deploy; by hand: `tar -czf - compose.prod.yaml docker/caddy deploy \| ssh … 'tar -xzf - -C /srv/mawred'` |
| `/srv/mawred/.env.production` | Secrets and settings (`deploy/env.production.example`). `chmod 600`, never in git, copy kept in the password manager |
| `/srv/mawred/.deploy/` | `current-tag`, `previous-tag`, the lock, alert markers, `drills.log` |
| Docker volumes | `pgsql`, `redis`, `storage` (uploads), `backups` (archives before they go offsite), `caddy_data` (certificates), `waha` (the paired phone), `clamav` |

## Scripts

| Script | Runs | Does |
|---|---|---|
| `harden.sh` | once, as root | Docker, deploy user, SSH keys only, UFW 22/80/443, fail2ban, unattended upgrades, swap, log rotation, UTC, timers |
| `deploy.sh <sha>` | CI or by hand | Migrate (expand-only), start the new app beside the old, wait for `/health/ready`, retire the old, move workers, smoke-test through Caddy, roll back by itself if that fails |
| `deploy.sh rollback` / `init <sha>` / `up` / `status` | by hand | See the header of `deploy.sh` |
| `backup.sh` | `mawred-backup.timer`, 01:30 UTC | Encrypted archive (database + uploads), copied offsite, heartbeat |
| `restore-drill.sh` | `mawred-restore-drill.timer`, the 3rd at 04:00 UTC | Restores the newest archive into a throwaway Postgres and checks it |
| `monitor.sh` | `mawred-monitor.timer`, every 5 minutes | Disk, memory, containers, pending reboot → email |
| `dc.sh …` | by hand | `docker compose` for production, pinned to the deployed tag (`dc.sh ps`, `dc.sh logs app`) |
| `ci-gate.sh` | the CI key's forced command | Lets CI only send the bundle and deploy a SHA |

Alerts go to `OPS_ALERT_EMAILS` through the app (`php artisan ops:alert`). The external uptime
ping on `https://<APP_HOST>/health/ready` covers the case where the app or the whole server is down.

## Runbooks

- [Provision a server](runbooks/provision-server.md)
- [Deploy and roll back](runbooks/deploy-and-rollback.md)
- [Backups, restores and drills](runbooks/backup-and-restore.md)
- [Monitoring and alerts](runbooks/monitoring.md)
- [Rotate APP_KEY](runbooks/rotate-app-key.md)
- [Rotate the WhatsApp (Official) token](runbooks/whatsapp-token-rotation.md)
- [Re-pair the unofficial WhatsApp driver (WAHA)](runbooks/waha-re-pair.md)
- [Scale out](runbooks/scale-out.md)
- [Go-live checklist](go-live-checklist.md) · [Memory and disk budget](sizing.md)
