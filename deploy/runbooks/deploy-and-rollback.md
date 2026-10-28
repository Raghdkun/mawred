# Deploy and roll back

## How a deploy runs (`deploy/deploy.sh <sha>`)

1. Pull `ghcr.io/raghdkun/mawred-app:<sha>`.
2. Run `php artisan migrate --force` in a one-off container of the **new** image. Migrations are
   expand-only (CLAUDE.md), so the running release keeps working against the new schema.
3. Start a second `app` container on the new image. Its entrypoint warms config, route, event and
   view caches.
4. Wait for Docker's health check and `/health/ready` (database, Redis, storage) inside it. If it
   never gets there, it is removed and the old release keeps serving: nothing else changed.
5. Caddy already balances to both (`dynamic a app`, health-checked); the old container stops with
   30 s to finish its requests.
6. Horizon, the scheduler, Reverb and SSR are recreated on the new image. Horizon finishes running
   jobs first (90 s grace).
7. Smoke check: `https://<APP_HOST>/health/ready` through Caddy. If it fails, the script rolls
   back to the previous tag by itself and emails `OPS_ALERT_EMAILS`.
8. `current-tag` and `previous-tag` are updated; images other than those two are pruned.

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
/srv/mawred/deploy/deploy.sh status
/srv/mawred/deploy/deploy.sh <full commit sha>
```

## Roll back

`/srv/mawred/deploy/deploy.sh rollback` returns to `previous-tag` without migrations (the schema
is backward compatible by rule). If a release wrote data the previous one can't read, fix forward
instead.

If **both** the deploy and its automatic rollback fail (you get "…and rollback … failed"):
1. `deploy.sh status`: which containers are up, and on which tag?
2. `deploy/dc.sh logs --tail 200 app`: what is failing? Usually `.env.production`, the
   database or Redis.
3. Start the last good tag directly: `MAWRED_TAG=<tag> deploy/dc.sh up -d app horizon scheduler reverb ssr`,
   then `printf '%s\n' <tag> > .deploy/current-tag`.
4. If the database is the problem, see [backups](backup-and-restore.md). Never run `migrate:rollback`
   in production.

## Other services

`deploy.sh` only replaces the Mawred containers. After changing `compose.prod.yaml` for Postgres,
Redis, Gotenberg, WAHA, ClamAV, GlitchTip or Caddy, run `deploy.sh up` in a quiet hour: it recreates
the services whose settings changed (a Postgres or Redis restart is a short outage). Caddyfile
changes are reloaded on every deploy without dropping connections.
