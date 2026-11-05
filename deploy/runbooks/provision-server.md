# Provision a server

For a Hostinger KVM 4 with Ubuntu 24.04. Needs: root SSH access, the domain's DNS, a GitHub
token that can read the GHCR package, and the values for `.env.production`.

**A server that already runs other sites** (D135): use `harden.sh --shared` in step 2. It checks
that Docker (with Compose), nginx and certbot are there and sets up only Mawred's parts: the
deploy user, `/core/mawred`, the nginx sudo rule and the timers. It leaves SSH, the firewall,
Docker's settings, packages, swap and kernel settings alone, because other sites depend on them.
Before step 5, check that ports 8070, 8081, 8082 and 8090 are free
(`ss -ltnp | grep -E ':(8070|8081|8082|8090) '`); if not, set `MAWRED_*_PORT` in `.env.production`.
Mawred's memory limits add up to about 10 GB ([sizing](../sizing.md)): check `free -h` leaves
that much beside the other sites.

1. **DNS.** Point `APP_HOST`, `CAREERS_HOST` and `GLITCHTIP_HOST` (A and AAAA) at the server.
2. **Harden.** From a checkout on your machine:
   ```bash
   tar -czf - compose.prod.yaml deploy | ssh root@<server> 'mkdir -p /root/mawred && tar -xzf - -C /root/mawred'
   ssh root@<server> '/root/mawred/deploy/harden.sh deploy /root/.ssh/authorized_keys /root/ci-deploy.pub'
   ```
   On a shared server: `harden.sh --shared deploy …` with the same arguments.
   The second key file is the **public** half of a key made only for GitHub Actions
   (`ssh-keygen -t ed25519 -f ci-deploy -C mawred-ci`); leave it out to add it later by re-running.
   Keep the root session open and check `ssh deploy@<server>` works from a new terminal.
   It installs nginx and certbot with apt (an nginx that is already there is kept, with its other
   sites), creates `/core/mawred` for the deploy user, and lets that user run only
   `nginx -t -q` and `systemctl reload nginx` as root.
3. **Bundle and secrets**, as `deploy`:
   ```bash
   tar -czf - compose.prod.yaml deploy | ssh deploy@<server> 'tar -xzf - -C /core/mawred'
   ```
   Then, still as `deploy`, generate `.env.production` on the server:
   ```bash
   /core/mawred/deploy/make-env.sh <app-host> <ops-email> [glitchtip-host]
   ```
   It fills in the hosts and the alert address and creates every secret Mawred makes for itself
   (`APP_KEY`, `BLIND_INDEX_KEY`, the database password, Reverb's keys, GlitchTip's key, the backup
   archive password) with `openssl`, prints none of them, and never overwrites an existing file.
   Fill in the lines it lists (Postmark, the From address, the backup bucket) with `nano`. VAPID
   keys come after the first start (`deploy/dc.sh exec app php artisan mawred:vapid-keys`). Put a
   copy of the file in the password manager **now**.
4. **Registry.** `echo <token> | docker login ghcr.io -u <github user> --password-stdin` (a
   fine-grained token with `read:packages` only).
5. **First start.** `/core/mawred/deploy/deploy.sh init <commit sha>` creates the GlitchTip
   database, migrates, starts every service with the app in the blue slot (127.0.0.1:8081) and
   checks `/health/ready`.
6. **nginx and HTTPS**, as root: `sudo /core/mawred/deploy/nginx-site.sh`. It gets a Let's Encrypt
   certificate for each host in `.env.production` (DNS must already point here and port 80 be
   open), writes `/etc/nginx/conf.d/mawred.conf`, tests it and reloads nginx. Then
   `curl -I https://<APP_HOST>/health/ready` should answer 200.
7. **Seed and owner.**
   ```bash
   /core/mawred/deploy/dc.sh exec app php artisan db:seed --class='Database\Seeders\Production\ProductionSeeder' --force
   /core/mawred/deploy/dc.sh exec app php artisan mawred:install --name='…' --email='…'
   ```
   Open the printed one-time link and finish setup in the browser.

   More admins later, without email: `deploy/dc.sh exec app php artisan mawred:create-admin
   --name='…' --email='…'` (Super Admin; add `--role=hr_admin` for HR) prints a one-time password
   link, valid 60 minutes; `--relink --email='…'` issues a fresh one (D136).
8. **Monitoring and backups.** Follow [monitoring](monitoring.md) (GlitchTip project, DSN, uptime
   ping) and [backups](backup-and-restore.md) (bucket, keys, lifecycle, first manual run).
9. Work through the [go-live checklist](../go-live-checklist.md).

`harden.sh` is safe to run again (for example to add the CI key or after changing the timers).
