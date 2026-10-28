# Provision a server

For a new Hostinger KVM 4 with Ubuntu 24.04. Needs: root SSH access, the domain's DNS, a GitHub
token that can read the GHCR package, and the values for `.env.production`.

1. **DNS.** Point `APP_HOST`, `CAREERS_HOST` and `GLITCHTIP_HOST` (A and AAAA) at the server.
2. **Harden.** From a checkout on your machine:
   ```bash
   tar -czf - compose.prod.yaml docker/caddy deploy | ssh root@<server> 'mkdir -p /root/mawred && tar -xzf - -C /root/mawred'
   ssh root@<server> '/root/mawred/deploy/harden.sh deploy /root/.ssh/authorized_keys /root/ci-deploy.pub'
   ```
   The second key file is the **public** half of a key made only for GitHub Actions
   (`ssh-keygen -t ed25519 -f ci-deploy -C mawred-ci`); leave it out to add it later by re-running.
   Keep the root session open and check `ssh deploy@<server>` works from a new terminal.
3. **Bundle and secrets**, as `deploy`:
   ```bash
   tar -czf - compose.prod.yaml docker/caddy deploy | ssh deploy@<server> 'tar -xzf - -C /srv/mawred'
   ```
   Create `/srv/mawred/.env.production` from `deploy/env.production.example` and `.env.example`
   (`chmod 600`). Generate `APP_KEY` with `php artisan key:generate --show`, `GLITCHTIP_SECRET_KEY`
   and `BACKUP_ARCHIVE_PASSWORD` with `openssl rand -base64 48`, VAPID keys with
   `php artisan mawred:vapid-keys`. Put a copy of the file in the password manager **now**.
4. **Registry.** `echo <token> | docker login ghcr.io -u <github user> --password-stdin` (a
   fine-grained token with `read:packages` only).
5. **First start.** `/srv/mawred/deploy/deploy.sh init <commit sha>` creates the GlitchTip
   database, migrates, starts every service and checks `/health/ready`.
6. **Seed and owner.**
   ```bash
   /srv/mawred/deploy/dc.sh exec app php artisan db:seed --class='Database\Seeders\Production\ProductionSeeder' --force
   /srv/mawred/deploy/dc.sh exec app php artisan mawred:install --name='…' --email='…'
   ```
   Open the printed one-time link and finish setup in the browser.
7. **Monitoring and backups.** Follow [monitoring](monitoring.md) (GlitchTip project, DSN, uptime
   ping) and [backups](backup-and-restore.md) (bucket, keys, lifecycle, first manual run).
8. Work through the [go-live checklist](../go-live-checklist.md).

`harden.sh` is safe to run again (for example to add the CI key or after changing the timers).
