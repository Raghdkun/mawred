# Scale out

One KVM 4 is sized for one legal entity with a few thousand employees ([sizing](../sizing.md)).
Move pieces off the box in this order, and only when the signals say so. Each step is a
configuration change; the app is already built for it.

## Signals

- Memory regularly under 10 % free, or Postgres using its full 2 GB with cache misses
  (Pulse → slow queries).
- Horizon wait times over a minute for `default` or `notifications` at busy hours.
- Request p95 over the budgets (Pulse) with CPU near 100 %.
- Disk: uploads growing past ~60 % of the volume.

## 1. Files to object storage (S3)

Uploads (`storage/app/private`) move to an S3-compatible bucket when disk is the constraint.
1. Add `league/flysystem-aws-s3-v3` (a new dependency: decide and note it in
   `docs/stack-versions.md`).
2. Point the private disk at S3 (`AWS_*` in `.env.production`, `FILESYSTEM_DISK`, and the
   media-library disk), copy the existing files with `rclone copy`, deploy.
3. Backups: files are then covered by the bucket's versioning; drop `storage_path('app')` from
   `config/backup.php` includes so archives hold only the database.

## 2. Postgres on its own server

When the database needs more memory or I/O than the box can give.
1. A managed Postgres 18 (or a second VPS) on a private network with the server, TLS on.
2. `pg_dump` / restore in a maintenance window (see [backups](backup-and-restore.md)), or logical
   replication to cut over with seconds of downtime.
3. Point `DB_HOST`/`DB_PORT`/`DB_SSLMODE=require`, deploy, remove `pgsql` from
   `compose.prod.yaml`. Backups keep working: `pg_dump` runs from the app image.
4. GlitchTip keeps its own database on the old box or moves alongside.

## 3. More app capacity

- Serve from both app slots at once: list both ports in nginx's `mawred_app` upstream
  (`.deploy/nginx-upstream.conf`) and change `deploy.sh` to replace one slot at a time, taking it
  out of the upstream while it restarts. A second app server joins the same way, over the private
  network.
- Move Horizon to a second server sharing Redis and Postgres over the private network. Keep the
  scheduler on exactly one machine (`onOneServer()` guards it, but one is simpler).
- Redis on its own server when queues and cache outgrow 512 MB; keep cache, queue and session on
  separate databases (already the case).

## 4. Read-heavy reports

Report previews and exports can read from a Postgres replica (`DB_READ_HOST`); exports already run
on the `reports` queue and can get their own Horizon supervisor.
