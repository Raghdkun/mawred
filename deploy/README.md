# Deploying Mawred

One Hostinger KVM 4 (Ubuntu 24.04) runs everything with Docker Compose (ADR-0019). The host's own
nginx (installed with apt) terminates HTTPS and proxies to the containers, which publish their
ports on 127.0.0.1 only (D134). GitHub Actions builds the image; the server only pulls it.
Nothing here runs anywhere until someone with server access does it.

## On the server

| Path | What |
|---|---|
| `/core/mawred/compose.prod.yaml`, `deploy/` | The deploy bundle. CI sends it before each deploy; by hand: `tar -czf - compose.prod.yaml deploy \| ssh … 'tar -xzf - -C /core/mawred'` |
| `/core/mawred/.env.production` | Secrets and settings (`deploy/env.production.example`). `chmod 600`, never in git, copy kept in the password manager |
| `/core/mawred/.deploy/` | `current-tag`, `previous-tag`, `live-slot`, `nginx-upstream.conf` (the live slot's port, included by nginx), the lock, alert markers, `drills.log` |
| `/etc/nginx/conf.d/mawred.conf`, `/etc/nginx/snippets/mawred-*.conf` | Written by `nginx-site.sh` from `deploy/nginx/`; edit those and run it again, never the server copies. Other sites on this nginx are left alone |
| `/etc/letsencrypt/` | Certificates (certbot, renewed by its own timer; a hook reloads nginx) |
| Docker volumes | `pgsql`, `redis`, `storage` (uploads), `backups` (archives before they go offsite), `waha` (the paired phone), `clamav` |

### Ports (127.0.0.1 only)

| Port | Service | nginx sends |
|---|---|---|
| 8081 / 8082 | `app-blue` / `app-green`: the live slot serves, the other is empty between deploys | everything on `APP_HOST` and `CAREERS_HOST` |
| 8090 | `reverb` | `/app/` WebSockets on `APP_HOST` |
| 8070 | `glitchtip` | everything on `GLITCHTIP_HOST` |

Change them with `MAWRED_*_PORT` in `.env.production` if something else on the server uses them,
then run `nginx-site.sh` and a deploy.

## Scripts

| Script | Runs | Does |
|---|---|---|
| `harden.sh` | once, as root | Docker, nginx and certbot, deploy user (may test and reload nginx), SSH keys only, UFW 22/80/443, fail2ban, unattended upgrades, swap, log rotation, UTC, timers |
| `nginx-site.sh` | as root, after `.env.production` exists and whenever a host or `deploy/nginx/` changes | Certificates for each host, the nginx site, the renewal hook |
| `deploy.sh <sha>` | CI or by hand | Migrate (expand-only), start the new release in the idle slot, wait for `/health/ready`, point nginx at it, retire the old slot, move workers, smoke-test through nginx, roll back by itself if that fails |
| `deploy.sh rollback` / `init <sha>` / `up` / `status` | by hand | See the header of `deploy.sh` |
| `backup.sh` | `mawred-backup.timer`, 01:30 UTC | Encrypted archive (database + uploads), copied offsite, heartbeat |
| `restore-drill.sh` | `mawred-restore-drill.timer`, the 3rd at 04:00 UTC | Restores the newest archive into a throwaway Postgres and checks it |
| `monitor.sh` | `mawred-monitor.timer`, every 5 minutes | Disk, memory, containers, nginx, pending reboot → email |
| `dc.sh …` | by hand | `docker compose` for production, pinned to the deployed tag; `app` means the live slot (`dc.sh ps`, `dc.sh logs app`) |
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
