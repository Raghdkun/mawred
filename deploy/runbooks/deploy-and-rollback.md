# Deploy and roll back

## How a deploy runs (`deploy/deploy.sh <sha>`)

1. Pull `ghcr.io/raghdkun/mawred-app:<sha>`.
2. Run `php artisan migrate --force` in a one-off container of the **new** image. Migrations are
   expand-only (CLAUDE.md), so the running release keeps working against the new schema.
3. Start the new image in the **idle slot** (D134): `app-green` on 127.0.0.1:8082 when `app-blue`
   on :8081 is live, and the other way round. Its entrypoint warms config, route, event and view
   caches.
4. Wait for Docker's health check and `/health/ready` (database, Redis, storage) on the slot's
   port. If it never gets there, it is removed and the old release keeps serving: nothing else
   changed.
5. Point nginx at the new slot: `.deploy/nginx-upstream.conf` gets its port, then
   `sudo nginx -t -q` and `sudo systemctl reload nginx` (the only root commands the deploy user may
   run). The reload is graceful: requests already on their way finish on the old slot. If nginx
   rejects the change, the old upstream is put back and the new slot removed.
6. Five seconds later the old slot stops, with 30 s to finish its requests.
7. Horizon, the scheduler, Reverb and SSR are recreated on the new image. Horizon finishes running
   jobs first (90 s grace).
8. Smoke check: `https://<APP_HOST>/health/ready` through nginx. If it fails, the script rolls
   back to the previous tag by itself (the same switch in reverse) and emails `OPS_ALERT_EMAILS`.
9. `current-tag`, `previous-tag` and `live-slot` are updated; images other than the current and
   previous tags are pruned.

## From GitHub Actions (D125)

`.github/workflows/deploy.yml` runs after CI passes on `main` (or by hand: Actions → Deploy →
Run workflow, with a ref). It builds the image, pushes it, then waits for approval in the
`production` environment before touching the server.

One-time setup in the repository settings:
- Environment `production` with required reviewers.
- Environment secrets: `DEPLOY_HOST`, `DEPLOY_USER` (`deploy`), `DEPLOY_SSH_KEY` (the private
  `ci-deploy` key), `DEPLOY_KNOWN_HOSTS` (`ssh-keyscan -t ed25519 <server>`, checked against the
  server's own fingerprint).
- The CI key is installed by `harden.sh` with a forced command (`deploy/ci-gate.sh`): it can only
  send the bundle and deploy a full commit SHA.

## By hand

```bash
ssh deploy@<server>
/core/mawred/deploy/deploy.sh status
/core/mawred/deploy/deploy.sh <full commit sha>
```

## Roll back

`/core/mawred/deploy/deploy.sh rollback` returns to `previous-tag` without migrations (the schema
is backward compatible by rule). If a release wrote data the previous one can't read, fix forward
instead.

If **both** the deploy and its automatic rollback fail (you get "…and rollback … failed"):
1. `deploy.sh status`: which slot is live, which containers are up, and on which tag?
   `cat .deploy/nginx-upstream.conf` shows the port nginx sends traffic to.
2. `deploy/dc.sh logs --tail 200 app-blue` (or `app-green`): what is failing? Usually
   `.env.production`, the database or Redis. `sudo tail /var/log/nginx/error.log` shows what
   nginx sees.
3. Start the last good tag in a slot and point nginx at it:
   `MAWRED_TAG=<tag> deploy/dc.sh up -d --no-deps app-blue horizon scheduler reverb ssr`, then
   `printf 'server 127.0.0.1:8081;\n' > .deploy/nginx-upstream.conf`, `sudo nginx -t -q`,
   `sudo systemctl reload nginx`, `printf 'blue\n' > .deploy/live-slot` and
   `printf '%s\n' <tag> > .deploy/current-tag`.
4. If the database is the problem, see [backups](backup-and-restore.md). Never run `migrate:rollback`
   in production.

## Other services

`deploy.sh` only replaces the Mawred containers. After changing `compose.prod.yaml` for Postgres,
Redis, Gotenberg, WAHA, ClamAV or GlitchTip, run `deploy.sh up` in a quiet hour: it recreates the
services whose settings changed (a Postgres or Redis restart is a short outage). Changes to the app
slots themselves take effect on the next deploy. After changing `deploy/nginx/` or a host name, run
`sudo /core/mawred/deploy/nginx-site.sh`: it tests the new config before reloading and keeps the
old one if nginx rejects it.
